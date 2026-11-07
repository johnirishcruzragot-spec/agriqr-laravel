<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'normalized_name', 'is_active'])]
class Barangay extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function farmers(): HasMany
    {
        return $this->hasMany(Farmer::class);
    }
}
