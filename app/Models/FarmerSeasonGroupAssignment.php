<?php

namespace App\Models;

use Database\Factories\FarmerSeasonGroupAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['farmer_id', 'season_group_id', 'assigned_by', 'started_at', 'ended_at'])]
class FarmerSeasonGroupAssignment extends Model
{
    /** @use HasFactory<FarmerSeasonGroupAssignmentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }

    public function seasonGroup(): BelongsTo
    {
        return $this->belongsTo(SeasonGroup::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
