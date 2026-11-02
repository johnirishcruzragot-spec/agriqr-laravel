<?php

namespace App\Models;

use Database\Factories\FarmerParcelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'farmer_id',
    'parcel_number',
    'parcel_address_1',
    'parcel_address_2',
    'parcel_address_3',
    'parcel_area',
    'commodity_name',
    'crop_area',
    'farm_type',
    'number_of_heads',
    'agency',
    'ownership_type',
    'owner_name',
])]
class FarmerParcel extends Model
{
    /** @use HasFactory<FarmerParcelFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'parcel_area' => 'decimal:4',
            'crop_area' => 'decimal:4',
            'number_of_heads' => 'decimal:2',
        ];
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }
}
