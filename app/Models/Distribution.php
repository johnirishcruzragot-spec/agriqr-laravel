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
        'processed_by',
        'eligibility_status',
        'transaction_status',
        'remarks',
        'distributed_at',
    ];

    protected function casts(): array
    {
        return ['distributed_at' => 'datetime'];
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(SubsidyProgram::class, 'subsidy_program_id');
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
