<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    /**
     * Only reservations that are still awaiting service or confirmed reserve a slot.
     * Terminal states (for example cancelled and rejected) must immediately free it.
     */
    public const BLOCKING_STATUSES = ['pending', 'confirmed'];
    public const PAYMENT_STATUSES = ['unpaid', 'paid'];
    public const CONFIRMED_RESCHEDULE_MIN_HOURS = 24;

    protected $fillable = [
        'user_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'court_id',
        'rebooked_from_booking_id',
        'business_id',
        'staff_id',
        'date',
        'time_slot',
        'duration_hours',
        'duration_minutes',
        'status',
        'payment_status',
        'booking_code',
        'public_token',
        'occupancy_key',
        'total_price',
    ];

    protected $casts = [
        'date' => 'datetime',
        'occupied_slot_at' => 'datetime',
        'completed_at' => 'datetime',
        'staff_id' => 'integer',
        'duration_hours' => 'integer',
        'duration_minutes' => 'integer',
    ];

    protected static function booted(): void
    {
        // Keeps occupied_slot_at (the DB-level double-booking backstop —
        // see the migration) in sync automatically on every save, so no
        // caller has to remember to maintain it by hand: a booking
        // occupies its slot only while in a blocking status.
        static::saving(function (Booking $booking) {
            $booking->occupied_slot_at = in_array($booking->status, self::BLOCKING_STATUSES, true)
                ? $booking->date
                : null;
            $booking->occupancy_key = $booking->staff_id
                ? 'staff:'.$booking->staff_id
                : 'court:'.$booking->court_id;
        });
    }

    public function user() { return $this->belongsTo(User::class); }
    public function court() { return $this->belongsTo(Court::class); }
    public function business() { return $this->belongsTo(Business::class); }
    public function staff() { return $this->belongsTo(Staff::class); }
    public function rebookedFrom() { return $this->belongsTo(self::class, 'rebooked_from_booking_id'); }
    public function rebookings() { return $this->hasMany(self::class, 'rebooked_from_booking_id'); }

    /**
     * Why this reservation cannot be rescheduled now, or null if it can.
     * Pending requests can be moved by the customer or the business. A
     * confirmed appointment is a commitment: only the business may move it,
     * and only with at least 24 hours' notice. $now is the business clock.
     */
    public function rescheduleBlockReason(bool $byManager, \Carbon\CarbonInterface $now): ?string
    {
        if ($this->status === 'pending') return null;
        if ($this->status !== 'confirmed') return 'Only pending or confirmed reservations can be rescheduled';
        if (!$byManager) return 'Only the business can reschedule a confirmed reservation';
        if ($now->diffInHours($this->date, false) < self::CONFIRMED_RESCHEDULE_MIN_HOURS) {
            return 'Confirmed reservations can only be rescheduled at least 24 hours in advance';
        }
        return null;
    }

    public function scopeBlocking($query)
    {
        return $query->whereIn('status', self::BLOCKING_STATUSES);
    }
}
