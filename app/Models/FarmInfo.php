<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'farmer_id',
    'farm_size_hectares',
    'farm_location',
    'tenure_type',
    'landowner_name',
    'landowner_contact',
    'crop_information',
    'remarks',
])]
class FarmInfo extends Model
{
    protected $table = 'farm_info';

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }
}
