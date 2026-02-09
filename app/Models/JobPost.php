<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'title',
        'department_id',
        'position_id',
        'employment_type',
        'openings',
        'posted_at',
        'closes_at',
        'status',
        'description',
    ];

    protected $casts = [
        'openings' => 'integer',
        'posted_at' => 'date',
        'closes_at' => 'date',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function candidates()
    {
        return $this->hasMany(Candidate::class);
    }
}

