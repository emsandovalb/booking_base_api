<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\\Database\\Factories\\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
        ];
    }

    /**
     * Platform-level flag. Grants access to the Super Admin panel only —
     * never a substitute for per-business membership on business_user.
     * Set exclusively via BootstrapAdminSeeder, never through user input.
     */
    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    public function tournaments()
    {
        return $this->hasMany(Tournament::class);
    }

    public function courts()
    {
        return $this->hasMany(Court::class, 'owner_id');
    }

    public function staff()
    {
        return $this->hasOne(Staff::class);
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class, 'team_user')
            ->using(TeamUser::class)
            ->withPivot(['role', 'status', 'joined_at'])
            ->withTimestamps();
    }

    public function businesses()
    {
        return $this->belongsToMany(Business::class, 'business_user')
            ->using(BusinessUser::class)
            ->withPivot(['role', 'status', 'invited_at', 'accepted_at', 'metadata'])
            ->withTimestamps();
    }

    public function activeBusinesses()
    {
        return $this->businesses()->wherePivot('status', 'active');
    }

    public function activeBusinessMembership(?Business $business): ?BusinessUser
    {
        if (!$business) {
            return null;
        }

        $membership = $this->businesses()
            ->where('businesses.id', $business->id)
            ->wherePivot('status', 'active')
            ->first();

        return $membership?->pivot instanceof BusinessUser ? $membership->pivot : null;
    }

    /**
     * Purely membership-based: does this user hold owner/admin on THIS
     * specific business's business_user pivot? Deliberately does not
     * consult the legacy `role` column or is_super_admin — a platform
     * super admin manages the platform via the Super Admin panel, not
     * individual businesses' operational data.
     */
    public function canManageBusiness(?Business $business): bool
    {
        $membership = $this->activeBusinessMembership($business);
        if (!$membership) {
            return false;
        }

        return in_array($membership->role, ['owner', 'admin'], true);
    }

    public function hasBusinessRole($business, array|string $roles): bool
    {
        $business = $this->resolveBusiness($business);
        if (!$business) {
            return false;
        }

        $roles = is_array($roles) ? $roles : [$roles];

        return $this->businesses()
            ->where('businesses.id', $business->id)
            ->wherePivot('status', 'active')
            ->wherePivotIn('role', $roles)
            ->exists();
    }

    public function belongsToBusiness($business): bool
    {
        $business = $this->resolveBusiness($business);
        if (!$business) {
            return false;
        }

        return $this->businesses()
            ->where('businesses.id', $business->id)
            ->wherePivot('status', 'active')
            ->exists();
    }

    private function resolveBusiness($business): ?Business
    {
        if ($business instanceof Business) {
            return $business;
        }

        if (is_numeric($business)) {
            return Business::query()->find((int) $business);
        }

        return null;
    }
}
