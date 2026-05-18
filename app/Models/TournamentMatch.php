<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TournamentMatch extends Model
{
    use HasFactory;

    protected $table = 'matches';

    protected $fillable = [
        'tournament_id',
        'tournament_stage_id',
        'court_id',
        'home_team_id',
        'away_team_id',
        'winner_team_id',
        'scheduled_at',
        'started_at',
        'finished_at',
        'round_number',
        'home_score',
        'away_score',
        'status',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'round_number' => 'integer',
        'home_score' => 'integer',
        'away_score' => 'integer',
        'metadata' => 'array',
    ];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function stage()
    {
        return $this->belongsTo(TournamentStage::class, 'tournament_stage_id');
    }

    public function court()
    {
        return $this->belongsTo(Court::class);
    }

    public function homeTeam()
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam()
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    public function winnerTeam()
    {
        return $this->belongsTo(Team::class, 'winner_team_id');
    }

    public function events()
    {
        return $this->hasMany(MatchEvent::class, 'match_id');
    }
}
