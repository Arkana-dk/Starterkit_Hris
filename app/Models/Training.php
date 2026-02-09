<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Training extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'title',
        'provider',
        'training_date',
        'duration_hours',
        'capacity',
        'status',
        'mandatory',
        'notes',
    ];

    protected $casts = [
        'training_date' => 'date',
        'duration_hours' => 'integer',
        'capacity' => 'integer',
        'mandatory' => 'boolean',
    ];
}

