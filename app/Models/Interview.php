<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Interview extends Model
{
    use HasFactory;

    protected $fillable = [
        'candidate_id',
        'interviewer_employee_id',
        'interview_date',
        'interview_time',
        'mode',
        'location',
        'result',
        'score',
        'notes',
    ];

    protected $casts = [
        'interview_date' => 'date',
        'score' => 'decimal:2',
    ];

    public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }

    public function interviewer()
    {
        return $this->belongsTo(Employee::class, 'interviewer_employee_id');
    }
}
