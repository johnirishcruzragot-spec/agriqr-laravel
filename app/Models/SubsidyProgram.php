<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'program_code',
    'program_name',
    'season_id',
    'requires_verified_rsbsa',
    'subsidy_type',
    'description',
    'start_date',
    'end_date',
    'eligibility_rules',
    'status',
    'created_by',
])]
class SubsidyProgram extends Model
{
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'requires_verified_rsbsa' => 'boolean',
        ];
    }

    public function supplies(): BelongsToMany
    {
        return $this->belongsToMany(Supply::class, 'subsidy_items')
            ->withPivot('maximum_quantity', 'unit_value')
            ->withTimestamps();
    }

    public function seasonGroups(): BelongsToMany
    {
        return $this->belongsToMany(SeasonGroup::class, 'program_season_groups')
            ->withPivot('rate_per_hectare')
            ->withTimestamps();
    }

    public function supplyGroupRates(): HasMany
    {
        return $this->hasMany(ProgramSupplyGroupRate::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(Distribution::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
