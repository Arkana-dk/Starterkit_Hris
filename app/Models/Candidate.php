<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Candidate extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'full_name',
        'email',
        'phone',
        'job_post_id',
        'stage',
        'source',
        'applied_at',
        'notes',
    ];

    protected $casts = [
        'applied_at' => 'date',
    ];

    public function jobPost()
    {
        return $this->belongsTo(JobPost::class);
    }

    public function interviews()
    {
        return $this->hasMany(Interview::class);
    }
}

