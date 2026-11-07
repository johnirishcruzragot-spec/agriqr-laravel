<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Distribution extends Model
{
    protected $fillable = [
        'transaction_number',
        'farmer_id',
        'subsidy_program_id',
        'season_id',
        'season_group_id',
        'processed_by',
        'eligibility_status',
        'transaction_status',
        'remarks',
        'distributed_at',
        'rate_per_hectare',
        'eligible_hectares',
        'face_verified_at',
        'face_verified_by',
        'value_cap',
        'assistance_value',
    ];

    protected function casts(): array
    {
        return [
            'distributed_at' => 'datetime',
            'rate_per_hectare' => 'decimal:2',
            'eligible_hectares' => 'decimal:4',
            'face_verified_at' => 'datetime',
            'value_cap' => 'decimal:2',
            'assistance_value' => 'decimal:2',
        ];
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(SubsidyProgram::class, 'subsidy_program_id');
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function seasonGroup(): BelongsTo
    {
        return $this->belongsTo(SeasonGroup::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DistributionItem::class);
    }
}
