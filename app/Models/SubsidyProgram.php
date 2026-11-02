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
        ];
    }

    public function supplies(): BelongsToMany
    {
        return $this->belongsToMany(Supply::class, 'subsidy_items')
            ->withPivot('maximum_quantity')
            ->withTimestamps();
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
