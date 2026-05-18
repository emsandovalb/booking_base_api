<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatchEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id',
        'team_id',
        'user_id',
        'event_type',
        'minute',
        'second',
        'period',
        'description',
        'occurred_at',
        'metadata',
    ];

    protected $casts = [
        'minute' => 'integer',
        'second' => 'integer',
        'occurred_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function match()
    {
        return $this->belongsTo(TournamentMatch::class, 'match_id');
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
