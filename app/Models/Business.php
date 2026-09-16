<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Business extends Model
{
    use HasFactory;

    private const SLUG_ALIASES = [
        'tres-amigos' => 'barberia-tres-amigos',
    ];

    protected $fillable = [
        'name',
        'slug',
        'legal_name',
        'business_type',
        'status',
        'app_config',
        'contact_config',
        'branding_config',
        'feature_config',
        'metadata',
    ];

    protected $casts = [
        'app_config' => 'array',
        'contact_config' => 'array',
        'branding_config' => 'array',
        'feature_config' => 'array',
        'metadata' => 'array',
    ];

    public function courts()
    {
        return $this->hasMany(Court::class);
    }

    public function staff()
    {
        return $this->hasMany(Staff::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'business_user')
            ->using(BusinessUser::class)
            ->withPivot(['role', 'status', 'invited_at', 'accepted_at', 'metadata'])
            ->withTimestamps();
    }

    public function members()
    {
        return $this->users();
    }

    public function activeUsers()
    {
        return $this->users()->wherePivot('status', 'active');
    }

    public function activeMembers()
    {
        return $this->activeUsers();
    }

    public function pendingMembers()
    {
        return $this->users()->wherePivot('status', 'pending');
    }

    public function suspendedMembers()
    {
        return $this->users()->wherePivot('status', 'suspended');
    }

    public function admins()
    {
        return $this->activeUsers()->wherePivotIn('role', ['owner', 'admin']);
    }

    public function activeAdmins()
    {
        return $this->admins();
    }

    public function owners()
    {
        return $this->activeUsers()->wherePivot('role', 'owner');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public static function resolveSlug(string $slug): string
    {
        $normalized = trim($slug);

        if ($normalized === '') {
            return $normalized;
        }

        return self::SLUG_ALIASES[$normalized] ?? $normalized;
    }

    public static function resolveBySlug(string $slug): ?self
    {
        $normalized = self::resolveSlug($slug);

        return self::query()
            ->active()
            ->where('slug', $normalized)
            ->first();
    }
}
