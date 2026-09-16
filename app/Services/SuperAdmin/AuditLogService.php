<?php

namespace App\Services\SuperAdmin;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class AuditLogService
{
    public function record(
        ?Business $business,
        ?User $user,
        string $action,
        ?Model $subject,
        array $oldValues = [],
        array $newValues = [],
        array $metadata = [],
    ): AuditLog {
        $request = request();

        return AuditLog::create([
            'business_id' => $business?->id,
            'user_id' => $user?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass() ?? ($business?->getMorphClass() ?? Business::class),
            'subject_id' => $subject?->getKey() ?? $business?->getKey(),
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'metadata' => $metadata ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    public function recentForBusiness(?Business $business, array $filters = [], int $limit = 20): Collection
    {
        $query = AuditLog::query()
            ->with(['business', 'user'])
            ->forBusiness($business)
            ->actionIs($filters['action'] ?? null)
            ->userIs($filters['user'] ?? null)
            ->onDate($filters['date'] ?? null)
            ->search($filters['search'] ?? null)
            ->latestFirst();

        if (!$business && !empty($filters['business'])) {
            $query->where('business_id', $filters['business']);
        }

        return $query->limit($limit)->get();
    }

    public function present(AuditLog $log): array
    {
        $businessName = $log->business?->name ?? 'Business';
        $actorName = $log->user?->name ?? 'System';
        $subjectName = $this->subjectName($log);
        $actionLabel = AuditLog::actionLabels()[$log->action] ?? str_replace('_', ' ', ucfirst($log->action));
        $icon = AuditLog::actionIcons()[$log->action] ?? '•';

        return [
            'icon' => $icon,
            'action' => $actionLabel,
            'actor' => $actorName,
            'business' => $businessName,
            'subject' => $subjectName,
            'headline' => $this->headline($log, $actorName, $businessName, $subjectName, $actionLabel),
            'detail' => $this->detail($log),
            'date' => $log->created_at?->format('d M Y, H:i') ?? 'N/A',
            'action_key' => $log->action,
        ];
    }

    private function subjectName(AuditLog $log): string
    {
        if ($log->subject instanceof Business) {
            return $log->subject->name;
        }

        if ($log->subject instanceof User) {
            return $log->subject->name;
        }

        if ($log->subject_type === Business::class || $log->subject_type === 'business') {
            return $log->business?->name ?? 'Business';
        }

        return class_basename((string) $log->subject_type);
    }

    private function headline(AuditLog $log, string $actorName, string $businessName, string $subjectName, string $actionLabel): string
    {
        return match ($log->action) {
            'business.created', 'business.wizard.created' => sprintf('%s created %s', $actorName, $businessName),
            'business.updated' => sprintf('%s updated %s', $actorName, $businessName),
            'workspace.configuration_updated' => sprintf('%s updated configuration for %s', $actorName, $businessName),
            'business.suspended' => sprintf('%s suspended %s', $actorName, $businessName),
            'business.activated' => sprintf('%s activated %s', $actorName, $businessName),
            'member.created' => sprintf('%s created %s', $actorName, $subjectName),
            'member.attached' => sprintf('%s attached %s to %s', $actorName, $subjectName, $businessName),
            'member.removed' => sprintf('%s removed %s from %s', $actorName, $subjectName, $businessName),
            'member.role_changed' => sprintf(
                '%s changed %s from %s to %s',
                $actorName,
                $subjectName,
                $this->humanize(data_get($log->old_values, 'role')),
                $this->humanize(data_get($log->new_values, 'role'))
            ),
            'member.status_changed' => sprintf(
                '%s changed %s status from %s to %s',
                $actorName,
                $subjectName,
                $this->humanize(data_get($log->old_values, 'status')),
                $this->humanize(data_get($log->new_values, 'status'))
            ),
            'member.owner_transferred' => sprintf('%s transferred ownership of %s', $actorName, $businessName),
            default => sprintf('%s performed %s on %s', $actorName, $actionLabel, $businessName),
        };
    }

    private function detail(AuditLog $log): string
    {
        $changes = [];

        foreach (['role', 'status', 'name', 'slug'] as $field) {
            $old = data_get($log->old_values, $field);
            $new = data_get($log->new_values, $field);

            if ($old === null && $new === null) {
                continue;
            }

            if ($old === $new) {
                continue;
            }

            $changes[] = sprintf('%s: %s -> %s', $this->humanize($field), $this->humanize($old), $this->humanize($new));
        }

        return implode(' | ', $changes);
    }

    private function humanize(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'N/A';
        }

        return str((string) $value)->replace('_', ' ')->title()->toString();
    }
}
