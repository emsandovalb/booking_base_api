<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class BusinessUser extends Pivot
{
    protected $table = 'business_user';

    protected $fillable = [
        'business_id',
        'user_id',
        'role',
        'status',
        'invited_at',
        'accepted_at',
        'metadata',
    ];

    protected $casts = [
        'invited_at' => 'datetime',
        'accepted_at' => 'datetime',
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
}
