<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'farmer_id',
    'rsbsa_number',
    'registration_status',
    'verification_status',
    'farm_size',
    'farm_size_unit',
    'crop_information',
    'registration_date',
    'remarks',
    'date_encoded',
    'ffrs_generated_number',
    'gender',
    'birthdate',
    'is_farmer',
    'is_farmworker',
    'is_fisherfolk',
    'is_agribusiness',
    'is_ip',
    'tribe',
    'is_organic_practitioner',
    'is_arb',
    'farmer_address_1',
    'farmer_address_2',
    'farmer_address_3',
])]
class RsbsaRecord extends Model
{
    protected function casts(): array
    {
        return [
            'registration_date' => 'date',
            'date_encoded' => 'date',
            'birthdate' => 'date',
            'is_farmer' => 'boolean',
            'is_farmworker' => 'boolean',
            'is_fisherfolk' => 'boolean',
            'is_agribusiness' => 'boolean',
            'is_ip' => 'boolean',
            'is_organic_practitioner' => 'boolean',
            'is_arb' => 'boolean',
        ];
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }
}
