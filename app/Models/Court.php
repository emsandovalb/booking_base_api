<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Court extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'category',
        'duration_hours',
        'price_per_hour',
        'rating',
        'lat',
        'lng',
        'facilities',
        'images',
        'owner_id',
        'contact_email',
        'contact_phone',
        'open_hour',
        'close_hour',
        'status',
    ];

    protected $casts = [
        'facilities' => 'array',
        'images' => 'array',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function tournaments()
    {
        return $this->hasMany(Tournament::class);
    }

    public function matches()
    {
        return $this->hasMany(TournamentMatch::class, 'court_id');
    }

    public function staffServices()
    {
        return $this->hasMany(StaffService::class, 'court_id');
    }

    public function staff()
    {
        return $this->belongsToMany(Staff::class, 'staff_services')
            ->withPivot(['is_primary'])
            ->withTimestamps();
    }
}
