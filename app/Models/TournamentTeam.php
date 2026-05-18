<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class TournamentTeam extends Pivot
{
    protected $table = 'tournament_teams';

    protected $fillable = [
        'tournament_id',
        'team_id',
        'status',
        'seed',
        'group_name',
        'registered_at',
        'checked_in_at',
        'eliminated_at',
    ];

    protected $casts = [
        'seed' => 'integer',
        'registered_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'eliminated_at' => 'datetime',
    ];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }
}
