<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'supply_code',
    'supply_name',
    'category',
    'unit',
    'description',
    'minimum_stock',
    'status',
])]
class Supply extends Model
{
    public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function subsidyPrograms(): BelongsToMany
    {
        return $this->belongsToMany(SubsidyProgram::class, 'subsidy_items')
            ->withPivot('maximum_quantity')
            ->withTimestamps();
    }
}
