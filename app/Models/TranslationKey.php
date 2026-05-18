<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TranslationKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'module',
        'description',
    ];

    /**
     * Translation values per language.
     */
    public function translations(): HasMany
    {
        return $this->hasMany(Translation::class);
    }
}
