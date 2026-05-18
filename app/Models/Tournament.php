<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Tournament extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'court_id',
        'name',
        'description',
        'format',
        'status',
        'entry_fee',
        'prize_pool',
        'max_teams',
        'registration_deadline',
        'starts_at',
        'ends_at',
        'rules',
        'settings',
        'cover_image',
    ];

    protected $appends = [
        'cover_image_url',
    ];

    protected $casts = [
        'entry_fee' => 'decimal:2',
        'prize_pool' => 'decimal:2',
        'max_teams' => 'integer',
        'registration_deadline' => 'datetime',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'settings' => 'array',
    ];

    public function organizer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function court()
    {
        return $this->belongsTo(Court::class);
    }

    public function stages()
    {
        return $this->hasMany(TournamentStage::class);
    }

    public function matches()
    {
        return $this->hasMany(TournamentMatch::class);
    }

    public function standings()
    {
        return $this->hasMany(Standing::class);
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class, 'tournament_teams')
            ->using(TournamentTeam::class)
            ->withPivot([
                'status',
                'seed',
                'group_name',
                'registered_at',
                'checked_in_at',
                'eliminated_at',
            ])
            ->withTimestamps();
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        return $this->cover_image ? Storage::url($this->cover_image) : null;
    }
}
