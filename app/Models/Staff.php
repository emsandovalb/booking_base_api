<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    use HasFactory;

    protected $table = 'staff';

    protected $fillable = [
        'user_id',
        'staff_role_id',
        'name',
        'email',
        'phone',
        'bio',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function role()
    {
        return $this->belongsTo(StaffRole::class, 'staff_role_id');
    }

    public function services()
    {
        return $this->hasMany(StaffService::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function courts()
    {
        return $this->belongsToMany(Court::class, 'staff_services')
            ->withPivot(['is_primary'])
            ->withTimestamps();
    }
}
