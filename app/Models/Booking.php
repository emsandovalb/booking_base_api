<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'court_id',
        'staff_id',
        'date',
        'time_slot',
        'duration_hours',
        'status',
        'booking_code',
        'total_price',
    ];

    protected $casts = [
        'date' => 'datetime',
        'duration_hours' => 'integer',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function court() { return $this->belongsTo(Court::class); }
    public function staff() { return $this->belongsTo(Staff::class); }
}
