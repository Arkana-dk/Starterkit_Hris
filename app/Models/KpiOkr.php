<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KpiOkr extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'title',
        'objective',
        'employee_id',
        'period_start',
        'period_end',
        'target_value',
        'current_value',
        'unit',
        'weight',
        'status',
        'is_active',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'target_value' => 'decimal:2',
        'current_value' => 'decimal:2',
        'weight' => 'integer',
        'is_active' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}

