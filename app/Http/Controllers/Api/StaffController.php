<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Models\Staff;
use App\Models\StaffRole;
use App\Models\StaffService;
use App\Support\BusinessContext;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        $context = BusinessContext::fromRequest($request);
        if (!$context->isValid()) {
            return response()->json(['message' => 'Business not found'], 404);
        }

        $perPage = $this->perPageFromRequest($request);
        $query = Staff::query()
            ->with(['role', 'user'])
            ->orderBy('name');

        if ($context->hasSlug()) {
            $query->where('business_id', $context->businessId());
            $query->withCount([
                'services as services_count' => function ($services) use ($context) {
                    $services->whereHas('resource', function ($resource) use ($context) {
                        $resource->where('business_id', $context->businessId());
                    });
                },
            ]);
            $query->with([
                'services' => function ($services) use ($context) {
                    $services->whereHas('resource', function ($resource) use ($context) {
                        $resource->where('business_id', $context->businessId());
                    })->with('resource');
                },
            ]);
        } else {
            $query->with(['services.resource']);
            $query->withCount('services');
        }

        $result = $query->paginate($perPage)->withQueryString();

        return $this->paginatedResponse($result);
    }

    public function roles()
    {
        return response()->json([
            'data' => StaffRole::query()
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function show(Request $request, Staff $staff)
    {
        return $this->loadStaff($staff);
    }

    public function store(Request $request)
    {
        $context = BusinessContext::fromRequest($request);
        if (!$context->isValid()) {
            return response()->json(['message' => 'Business not found'], 404);
        }
        if ($response = $this->authorizeBusinessAdmin($request, $context)) {
            return $response;
        }

        $data = $this->validateStaff($request);
        if ($context->hasSlug()) {
            $data['business_id'] = $context->currentBusinessId();
        }
        $staff = Staff::create($data);

        return response()->json($this->loadStaff($staff), 201);
    }

    public function update(Request $request, Staff $staff)
    {
        $context = BusinessContext::fromRequest($request);
        if (!$context->isValid()) {
            return response()->json(['message' => 'Business not found'], 404);
        }
        if ($response = $this->authorizeBusinessAdmin($request, $context)) {
            return $response;
        }

        $data = $this->validateStaff($request, true);
        $staff->fill($data);
        $staff->save();

        return response()->json($this->loadStaff($staff->fresh()));
    }

    public function destroy(Request $request, Staff $staff)
    {
        return $this->deactivate($request, $staff);
    }

    public function deactivate(Request $request, Staff $staff)
    {
        $context = BusinessContext::fromRequest($request);
        if (!$context->isValid()) {
            return response()->json(['message' => 'Business not found'], 404);
        }
        if ($response = $this->authorizeBusinessAdmin($request, $context)) {
            return $response;
        }

        $staff->is_active = false;
        $staff->save();

        return response()->json($this->loadStaff($staff->fresh()));
    }

    public function attachService(Request $request, Staff $staff)
    {
        $context = BusinessContext::fromRequest($request);
        if (!$context->isValid()) {
            return response()->json(['message' => 'Business not found'], 404);
        }
        if ($response = $this->authorizeBusinessAdmin($request, $context)) {
            return $response;
        }

        if ($context->hasSlug() && $staff->business_id !== $context->currentBusinessId()) {
            return response()->json(['message' => 'Staff not found'], 404);
        }

        $data = $request->validate([
            'resource_id' => ['required', 'integer'],
            'is_primary' => ['sometimes', 'boolean'],
        ]);

        $resourceQuery = Court::query()->whereKey($data['resource_id']);
        $context->applyTo($resourceQuery);
        $resource = $resourceQuery->firstOrFail();
        $existing = StaffService::query()
            ->where('staff_id', $staff->id)
            ->where('court_id', $resource->id)
            ->first();

        $isPrimary = array_key_exists('is_primary', $data)
            ? (bool) $data['is_primary']
            : !$staff->services()->exists();

        if ($existing) {
            $existing->is_primary = $isPrimary;
            $existing->save();
        } else {
            $existing = StaffService::create([
                'staff_id' => $staff->id,
                'court_id' => $resource->id,
                'is_primary' => $isPrimary,
            ]);
        }

        $this->syncPrimaryService($staff->id, $existing->id, $isPrimary);

        return response()->json($this->loadStaff($staff->fresh()));
    }

    public function detachService(Request $request, Staff $staff, int $resourceId)
    {
        $context = BusinessContext::fromRequest($request);
        if (!$context->isValid()) {
            return response()->json(['message' => 'Business not found'], 404);
        }
        if ($response = $this->authorizeBusinessAdmin($request, $context)) {
            return $response;
        }

        if ($context->hasSlug() && $staff->business_id !== $context->currentBusinessId()) {
            return response()->json(['message' => 'Staff not found'], 404);
        }

        $resourceQuery = Court::query()->whereKey($resourceId);
        $context->applyTo($resourceQuery);
        $resource = $resourceQuery->firstOrFail();

        $service = StaffService::query()
            ->where('staff_id', $staff->id)
            ->where('court_id', $resource->id)
            ->first();

        if (!$service) {
            return response()->json(['message' => 'Assignment not found'], 404);
        }

        $wasPrimary = (bool) $service->is_primary;
        $service->delete();

        if ($wasPrimary) {
            $this->promotePrimaryService($staff->id);
        }

        return response()->json($this->loadStaff($staff->fresh()));
    }

    private function validateStaff(Request $request, bool $partial = false): array
    {
        $rules = [
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'email' => [$partial ? 'sometimes' : 'nullable', 'nullable', 'email', 'max:255'],
            'phone' => [$partial ? 'sometimes' : 'nullable', 'nullable', 'string', 'max:255'],
            'bio' => [$partial ? 'sometimes' : 'nullable', 'nullable', 'string'],
            'avatar' => [$partial ? 'sometimes' : 'nullable', 'nullable', 'string', 'max:2048'],
            'staff_role_id' => [$partial ? 'sometimes' : 'required', 'integer', 'exists:staff_roles,id'],
            'is_active' => [$partial ? 'sometimes' : 'boolean'],
            'user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ];

        $data = $request->validate($rules);
        if (!array_key_exists('is_active', $data) && !$partial) {
            $data['is_active'] = true;
        }

        return $data;
    }

    private function isAdmin(Request $request): bool
    {
        return ($request->user()?->role ?? null) === 'admin';
    }

    private function authorizeBusinessAdmin(Request $request, BusinessContext $context): ?\Illuminate\Http\JsonResponse
    {
        if (!$this->isAdmin($request)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (!$context->hasSlug()) {
            return null;
        }

        $business = $context->currentBusiness();
        if (!$business) {
            return response()->json(['message' => 'Business not found'], 404);
        }

        if (!$context->userCanManageBusiness($request->user(), $business)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return null;
    }

    private function loadStaff(Staff $staff): Staff
    {
        return $staff->load(['role', 'user', 'services.resource'])->loadCount('services');
    }

    private function syncPrimaryService(int $staffId, int $serviceId, bool $isPrimary): void
    {
        if (!$isPrimary) {
            return;
        }

        StaffService::query()
            ->where('staff_id', $staffId)
            ->where('id', '!=', $serviceId)
            ->update(['is_primary' => false]);
    }

    private function promotePrimaryService(int $staffId): void
    {
        $next = StaffService::query()
            ->where('staff_id', $staffId)
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->first();

        if ($next) {
            $next->is_primary = true;
            $next->save();
        }
    }

    private function perPageFromRequest(Request $request): int
    {
        $per = (int) $request->query('per_page', 20);
        if ($per < 1) {
            $per = 1;
        }
        if ($per > 50) {
            $per = 50;
        }

        return $per;
    }

    private function paginatedResponse($paginator)
    {
        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'next_page_url' => $paginator->nextPageUrl(),
                'prev_page_url' => $paginator->previousPageUrl(),
            ],
        ]);
    }
}
