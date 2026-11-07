<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistributionItem extends Model
{
    protected $fillable = [
        'distribution_id',
        'supply_id',
        'quantity',
        'quantity_rate_per_hectare',
        'quantity_cap',
        'maximum_quantity_per_farmer',
        'computed_quantity',
        'reduction_reason',
        'unit',
        'unit_value',
        'line_value',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'quantity_rate_per_hectare' => 'decimal:4',
            'quantity_cap' => 'decimal:2',
            'maximum_quantity_per_farmer' => 'decimal:2',
            'computed_quantity' => 'decimal:2',
            'unit_value' => 'decimal:2',
            'line_value' => 'decimal:2',
        ];
    }

    public function distribution(): BelongsTo
    {
        return $this->belongsTo(Distribution::class);
    }

    public function supply(): BelongsTo
    {
        return $this->belongsTo(Supply::class);
    }
}
