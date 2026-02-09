<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Approval extends Model
{
    use HasFactory;

    protected $fillable = [
        'status',
        'current_step',
        'requested_by_user_id',
        'requested_at',
        'final_decided_at',
        'notes',
    ];

    protected $casts = [
        'current_step' => 'integer',
        'requested_at' => 'datetime',
        'final_decided_at' => 'datetime',
    ];

    public function approvable()
    {
        return $this->morphTo();
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function steps()
    {
        return $this->hasMany(ApprovalStep::class);
    }
}
