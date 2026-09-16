<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\User;
use App\Services\SuperAdmin\AuditLogService;
use App\Support\CaseInsensitiveSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BusinessMemberController extends Controller
{
    public function index(Business $business, Request $request)
    {
        $business->loadCount([
            'users as members_count',
            'users as active_members_count' => fn ($query) => $query->wherePivot('status', 'active'),
            'users as pending_members_count' => fn ($query) => $query->wherePivot('status', 'pending'),
            'users as admins_count' => fn ($query) => $query->wherePivot('status', 'active')->wherePivotIn('role', ['owner', 'admin']),
        ]);

        $query = $business->users()
            ->select('users.id', 'users.name', 'users.email', 'users.created_at')
            ->withPivot(['role', 'status', 'invited_at', 'accepted_at', 'metadata', 'created_at'])
            ->orderBy('users.name');

        if ($search = trim((string) $request->string('search'))) {
            $query->where(function ($memberQuery) use ($search) {
                CaseInsensitiveSearch::apply($memberQuery, 'users.name', $search);
                CaseInsensitiveSearch::orWhere($memberQuery, 'users.email', $search);
            });
        }

        if ($role = $request->string('role')->toString()) {
            $query->wherePivot('role', $role);
        }

        if ($status = $request->string('status')->toString()) {
            $query->wherePivot('status', $status);
        }

        $members = $query->paginate(12)->withQueryString();

        return view('super-admin.business-members.index', [
            'business' => $business,
            'members' => $members,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'role' => $request->string('role')->toString(),
                'status' => $request->string('status')->toString(),
            ],
            'roleOptions' => $this->roleOptions(),
            'statusOptions' => $this->statusOptions(),
            'roleBadges' => $this->roleBadges(),
            'statusBadges' => $this->statusBadges(),
        ]);
    }

    public function create(Business $business)
    {
        return view('super-admin.business-members.create', [
            'business' => $business,
            'form' => $this->memberFormDefaults(),
            'roleOptions' => $this->roleOptions(),
            'statusOptions' => $this->statusOptions(),
            'roleBadges' => $this->roleBadges(),
            'statusBadges' => $this->statusBadges(),
        ]);
    }

    public function store(Request $request, Business $business, AuditLogService $auditLogService)
    {
        $data = $this->validateMember($request);

        $user = DB::transaction(function () use ($business, $data, $request, $auditLogService) {
            $existingUser = User::query()->where('email', $data['email'])->first();
            $user = $existingUser ?? User::create([
                'name' => $data['full_name'],
                'email' => $data['email'],
                'password' => $data['temporary_password'],
            ]);

            if (!$existingUser) {
                $auditLogService->record(
                    $business,
                    $request->user(),
                    'member.created',
                    $user,
                    [],
                    [
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $data['role'],
                        'status' => $data['status'],
                    ],
                    [
                        'source' => 'super-admin-members',
                        'created_user' => true,
                    ]
                );
            }

            $membershipData = $this->membershipPayload($data, $request);

            $business->users()->syncWithoutDetaching([
                $user->id => $membershipData,
            ]);

            $auditLogService->record(
                $business,
                $request->user(),
                'member.attached',
                $user,
                [],
                $this->membershipSnapshot($membershipData),
                [
                    'source' => 'super-admin-members',
                    'send_invitation_later' => $request->boolean('send_invitation_later'),
                ]
            );

            if ($existingUser) {
                $user->refresh();
            }

            return $user;
        });

        return redirect()
            ->route('super-admin.businesses.members.index', $business)
            ->with('status', $user->wasRecentlyCreated
                ? 'Member created and attached to business.'
                : 'Existing user attached to business.');
    }

    public function edit(Business $business, User $user)
    {
        $member = $this->resolveMember($business, $user);

        return view('super-admin.business-members.edit', [
            'business' => $business,
            'member' => $member,
            'form' => $this->memberFormDefaults($member),
            'roleOptions' => $this->roleOptions(),
            'statusOptions' => $this->statusOptions(),
            'roleBadges' => $this->roleBadges(),
            'statusBadges' => $this->statusBadges(),
        ]);
    }

    public function update(Request $request, Business $business, User $user, AuditLogService $auditLogService)
    {
        $member = $this->resolveMember($business, $user);
        $data = $this->validateUpdate($request);

        $this->guardOwnerRules($business, $member, $data['role']);

        $before = $this->pivotSnapshot($member);
        $membershipData = $this->membershipPayloadForUpdate($member, $data);

        $business->users()->updateExistingPivot($member->id, $membershipData);

        $after = $this->membershipSnapshot($membershipData);

        if ($before['role'] !== $after['role']) {
            $auditLogService->record(
                $business,
                $request->user(),
                'member.role_changed',
                $member,
                ['role' => $before['role']],
                ['role' => $after['role']],
                ['source' => 'super-admin-members']
            );
        }

        if ($before['status'] !== $after['status']) {
            $auditLogService->record(
                $business,
                $request->user(),
                'member.status_changed',
                $member,
                ['status' => $before['status']],
                ['status' => $after['status']],
                ['source' => 'super-admin-members']
            );
        }

        return redirect()
            ->route('super-admin.businesses.members.index', $business)
            ->with('status', 'Member updated.');
    }

    public function status(Request $request, Business $business, User $user, AuditLogService $auditLogService)
    {
        $member = $this->resolveMember($business, $user);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys($this->statusOptions()))],
        ]);

        $this->guardOwnerRules($business, $member, $member->pivot->role);

        $before = $this->pivotSnapshot($member);
        $membershipData = $this->membershipPayloadForStatus($member, $data['status']);

        $business->users()->updateExistingPivot($member->id, $membershipData);

        $auditLogService->record(
            $business,
            $request->user(),
            'member.status_changed',
            $member,
            ['status' => $before['status']],
            ['status' => $data['status']],
            ['source' => 'super-admin-members']
        );

        return back()->with('status', 'Member status updated.');
    }

    public function destroy(Request $request, Business $business, User $user, AuditLogService $auditLogService)
    {
        $member = $this->resolveMember($business, $user);

        $this->guardOwnerRules($business, $member, null, true);

        $before = $this->pivotSnapshot($member);
        $business->users()->detach($member->id);

        $auditLogService->record(
            $business,
            $request->user(),
            'member.removed',
            $member,
            $before,
            [],
            ['source' => 'super-admin-members']
        );

        return redirect()
            ->route('super-admin.businesses.members.index', $business)
            ->with('status', 'Membership removed. User preserved.');
    }

    private function validateMember(Request $request): array
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'temporary_password' => ['nullable', 'string', 'min:8', 'max:255'],
            'role' => ['required', Rule::in(array_keys($this->roleOptions()))],
            'status' => ['required', Rule::in(array_keys($this->statusOptions()))],
            'send_invitation_later' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'json'],
        ]);

        if (!User::query()->where('email', $data['email'])->exists() && empty($data['temporary_password'])) {
            throw ValidationException::withMessages([
                'temporary_password' => 'Temporary password is required for new users.',
            ]);
        }

        return $data;
    }

    private function validateUpdate(Request $request): array
    {
        return $request->validate([
            'role' => ['required', Rule::in(array_keys($this->roleOptions()))],
            'status' => ['required', Rule::in(array_keys($this->statusOptions()))],
            'metadata' => ['nullable', 'json'],
        ]);
    }

    private function membershipPayload(array $data, Request $request): array
    {
        $metadata = $this->decodeMetadata($data['metadata'] ?? null);
        $metadata = array_merge($metadata, [
            'created_by' => $request->user()?->id,
            'send_invitation_later' => $request->boolean('send_invitation_later'),
        ]);

        return [
            'role' => $data['role'],
            'status' => $data['status'],
            'invited_at' => $data['status'] === 'pending' || $request->boolean('send_invitation_later')
                ? now()
                : null,
            'accepted_at' => $data['status'] === 'active' ? now() : null,
            'metadata' => $metadata,
        ];
    }

    private function membershipPayloadForUpdate(User $member, array $data): array
    {
        $metadata = $this->decodeMetadata($data['metadata'] ?? null);
        $currentMetadata = is_array($member->pivot->metadata ?? null) ? $member->pivot->metadata : [];
        $statusPayload = $this->membershipPayloadForStatus($member, $data['status']);

        return [
            'role' => $data['role'],
            'status' => $data['status'],
            'invited_at' => $statusPayload['invited_at'] ?? $member->pivot->invited_at,
            'accepted_at' => $statusPayload['accepted_at'] ?? $member->pivot->accepted_at,
            'metadata' => array_replace($currentMetadata, $metadata),
        ];
    }

    private function membershipPayloadForStatus(User $member, string $status): array
    {
        $payload = [
            'status' => $status,
        ];

        if ($status === 'active' && !$member->pivot->accepted_at) {
            $payload['accepted_at'] = now();
        }

        if ($status === 'pending' && !$member->pivot->invited_at) {
            $payload['invited_at'] = now();
        }

        return $payload;
    }

    private function resolveMember(Business $business, User $user): User
    {
        $member = $business->users()
            ->whereKey($user->id)
            ->first();

        abort_if(!$member, 404);

        return $member;
    }

    private function guardOwnerRules(Business $business, User $member, ?string $newRole, bool $deleting = false): void
    {
        $isOwner = $member->pivot->role === 'owner';
        $ownerCount = $business->users()
            ->wherePivot('role', 'owner')
            ->count();

        if ($isOwner && $ownerCount <= 1 && $deleting) {
            throw ValidationException::withMessages([
                'role' => 'Cannot delete the last owner.',
            ]);
        }

        if ($isOwner && $ownerCount <= 1 && $newRole !== null && $newRole !== 'owner') {
            throw ValidationException::withMessages([
                'role' => 'Cannot demote the last owner.',
            ]);
        }
    }

    private function decodeMetadata(?string $metadata): array
    {
        if ($metadata === null || trim($metadata) === '') {
            return [];
        }

        return json_decode($metadata, true, 512, JSON_THROW_ON_ERROR);
    }

    private function memberFormDefaults(?User $member = null): array
    {
        return [
            'full_name' => $member?->name ?? '',
            'email' => $member?->email ?? '',
            'temporary_password' => Str::random(12),
            'role' => $member?->pivot->role ?? 'viewer',
            'status' => $member?->pivot->status ?? 'active',
            'send_invitation_later' => $member ? false : true,
            'metadata' => $member ? json_encode($member->pivot->metadata ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '{}',
        ];
    }

    private function roleOptions(): array
    {
        return [
            'owner' => 'Owner',
            'admin' => 'Admin',
            'manager' => 'Manager',
            'staff' => 'Staff',
            'viewer' => 'Viewer',
            'client' => 'Client',
        ];
    }

    private function statusOptions(): array
    {
        return [
            'active' => 'Active',
            'pending' => 'Pending',
            'suspended' => 'Suspended',
        ];
    }

    private function roleBadges(): array
    {
        return [
            'owner' => 'role-owner',
            'admin' => 'role-admin',
            'manager' => 'role-manager',
            'staff' => 'role-staff',
            'viewer' => 'role-viewer',
            'client' => 'role-client',
        ];
    }

    private function statusBadges(): array
    {
        return [
            'active' => 'status-active',
            'pending' => 'status-pending',
            'suspended' => 'status-suspended',
        ];
    }

    private function pivotSnapshot(User $member): array
    {
        return [
            'role' => $member->pivot->role,
            'status' => $member->pivot->status,
            'invited_at' => $member->pivot->invited_at,
            'accepted_at' => $member->pivot->accepted_at,
            'metadata' => $member->pivot->metadata,
        ];
    }

    private function membershipSnapshot(array $membershipData): array
    {
        return [
            'role' => $membershipData['role'] ?? null,
            'status' => $membershipData['status'] ?? null,
            'invited_at' => $membershipData['invited_at'] ?? null,
            'accepted_at' => $membershipData['accepted_at'] ?? null,
            'metadata' => $membershipData['metadata'] ?? null,
        ];
    }
}
