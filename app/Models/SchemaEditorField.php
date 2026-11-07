<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchemaEditorField extends Model
{
    protected $fillable = [
        'table_name',
        'column_name',
        'column_type',
        'created_by',
    ];
}
