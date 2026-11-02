<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
    'municipality',
    'province',
    'contact_number',
    'farm_information',
    'qr_code_path',
    'photo_path',
    'status',
])]
class Farmer extends Model
{
    use HasFactory;

    protected $table = 'farmers';

    protected $primaryKey = 'id';

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

    public function rsbsaRecord(): HasOne
    {
        return $this->hasOne(RsbsaRecord::class);
    }

    public function parcels(): HasMany
    {
        return $this->hasMany(FarmerParcel::class);
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(Distribution::class);
    }
}
