<?php

namespace App\Models;

use Database\Factories\SeasonGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['season_id', 'seed_type'])]
class SeasonGroup extends Model
{
    public const SEED_TYPES = ['hybrid', 'inbred'];

    /** @use HasFactory<SeasonGroupFactory> */
    use HasFactory;

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(FarmerSeasonGroupAssignment::class);
    }

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(SubsidyProgram::class, 'program_season_groups')
            ->withPivot('rate_per_hectare')
            ->withTimestamps();
    }
}
