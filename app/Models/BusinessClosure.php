<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessClosure extends Model
{
    protected $fillable = [
        'business_id',
        'staff_id',
        'date',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Closures affecting a given staff member on a date: business-wide ones plus that staff's own. */
    public function scopeAffecting($query, int $businessId, string $date, ?int $staffId)
    {
        return $query->where('business_id', $businessId)
            ->where('date', $date)
            ->where(function ($q) use ($staffId) {
                $q->whereNull('staff_id');
                if ($staffId) {
                    $q->orWhere('staff_id', $staffId);
                }
            });
    }
}
