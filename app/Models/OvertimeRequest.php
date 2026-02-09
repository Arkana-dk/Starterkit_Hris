<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OvertimeRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'work_date',
        'start_time',
        'end_time',
        'total_hours',
        'reason',
        'status',
        'approved_by_user_id',
        'approved_at',
        'approval_notes',
        'requested_at',
    ];

    protected $casts = [
        'work_date' => 'date',
        'total_hours' => 'decimal:2',
        'approved_at' => 'datetime',
        'requested_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
