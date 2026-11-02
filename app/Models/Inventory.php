<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventory extends Model
{
    protected $fillable = ['supply_id', 'quantity', 'reserved_quantity', 'last_updated_by'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'reserved_quantity' => 'decimal:2',
        ];
    }

    public function supply(): BelongsTo
    {
        return $this->belongsTo(Supply::class);
    }
}
