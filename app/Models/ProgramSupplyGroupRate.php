<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'subsidy_program_id',
    'supply_id',
    'season_group_id',
    'quantity_per_hectare',
    'maximum_quantity_per_farmer',
])]
class ProgramSupplyGroupRate extends Model
{
    protected function casts(): array
    {
        return [
            'quantity_per_hectare' => 'decimal:4',
            'maximum_quantity_per_farmer' => 'decimal:2',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(SubsidyProgram::class, 'subsidy_program_id');
    }

    public function supply(): BelongsTo
    {
        return $this->belongsTo(Supply::class);
    }

    public function seasonGroup(): BelongsTo
    {
        return $this->belongsTo(SeasonGroup::class);
    }
}
