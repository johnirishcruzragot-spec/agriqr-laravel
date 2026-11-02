<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistributionItem extends Model
{
    protected $fillable = ['distribution_id', 'supply_id', 'quantity', 'unit'];

    public function distribution(): BelongsTo
    {
        return $this->belongsTo(Distribution::class);
    }

    public function supply(): BelongsTo
    {
        return $this->belongsTo(Supply::class);
    }
}
