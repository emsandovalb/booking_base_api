<?php

namespace App\Models;

use App\Support\CaseInsensitiveSearch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'old_values',
        'new_values',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForBusiness($query, ?Business $business)
    {
        if (!$business) {
            return $query;
        }

        return $query->where('business_id', $business->id);
    }

    public function scopeActionIs($query, ?string $action)
    {
        if (!$action) {
            return $query;
        }

        return $query->where('action', $action);
    }

    public function scopeUserIs($query, mixed $userId)
    {
        if (!$userId) {
            return $query;
        }

        return $query->where('user_id', $userId);
    }

    public function scopeOnDate($query, ?string $date)
    {
        if (!$date) {
            return $query;
        }

        return $query->whereDate('created_at', $date);
    }

    public function scopeSearch($query, ?string $term)
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function ($searchQuery) use ($term) {
            CaseInsensitiveSearch::apply($searchQuery, 'action', $term);
            $searchQuery->orWhereHas('business', function ($businessQuery) use ($term) {
                CaseInsensitiveSearch::apply($businessQuery, 'name', $term);
                CaseInsensitiveSearch::orWhere($businessQuery, 'slug', $term);
            })
                ->orWhereHas('user', function ($userQuery) use ($term) {
                    CaseInsensitiveSearch::apply($userQuery, 'name', $term);
                    CaseInsensitiveSearch::orWhere($userQuery, 'email', $term);
                });
        });
    }

    public function scopeLatestFirst($query)
    {
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    public static function actionLabels(): array
    {
        return [
            'business.created' => 'Business created',
            'business.updated' => 'Business updated',
            'business.suspended' => 'Business suspended',
            'business.activated' => 'Business activated',
            'business.wizard.created' => 'Business created through wizard',
            'workspace.configuration_updated' => 'Workspace configuration updated',
            'member.created' => 'Member created',
            'member.attached' => 'Member attached',
            'member.removed' => 'Member removed',
            'member.role_changed' => 'Role changed',
            'member.status_changed' => 'Status changed',
            'member.owner_transferred' => 'Owner transferred',
        ];
    }

    public static function actionIcons(): array
    {
        return [
            'business.created' => '+',
            'business.updated' => '✏',
            'business.suspended' => '⛔',
            'business.activated' => '✓',
            'business.wizard.created' => '+',
            'workspace.configuration_updated' => '✏',
            'member.created' => '👤+',
            'member.attached' => '👤+',
            'member.removed' => '👤−',
            'member.role_changed' => '⇄',
            'member.status_changed' => '●',
            'member.owner_transferred' => '⇄',
        ];
    }

    public static function actionOptions(): array
    {
        return self::actionLabels();
    }
}
