<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'farmer_id',
    'first_name',
    'middle_name',
    'last_name',
    'suffix',
    'address',
    'barangay',
    'barangay_id',
    'municipality',
    'province',
    'contact_number',
    'farm_information',
    'custom_fields',
    'qr_code_path',
    'photo_path',
    'status',
    'deceased_at',
])]
class Farmer extends Model
{
    use HasFactory;

    protected $table = 'farmers';

    protected $primaryKey = 'id';

    protected function casts(): array
    {
        return [
            'custom_fields' => 'array',
            'deceased_at' => 'date',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return collect([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
            $this->suffix,
        ])->filter()->implode(' ');
    }

    public function farmInfo(): HasOne
    {
        return $this->hasOne(FarmInfo::class);
    }

    public function barangayMaster(): BelongsTo
    {
        return $this->belongsTo(Barangay::class, 'barangay_id');
    }

    public function rsbsaRecord(): HasOne
    {
        return $this->hasOne(RsbsaRecord::class);
    }

    public function parcels(): HasMany
    {
        return $this->hasMany(FarmerParcel::class);
    }

    public function seasonGroupAssignments(): HasMany
    {
        return $this->hasMany(FarmerSeasonGroupAssignment::class)
            ->orderByDesc('started_at')
            ->orderByDesc('id');
    }

    public function currentSeasonGroupAssignments(): HasMany
    {
        return $this->hasMany(FarmerSeasonGroupAssignment::class)->whereNull('ended_at');
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(Distribution::class);
    }
}
