<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'approval_id',
        'step',
        'approver_user_id',
        'status',
        'decided_at',
        'notes',
    ];

    protected $casts = [
        'step' => 'integer',
        'decided_at' => 'datetime',
    ];

    public function approval()
    {
        return $this->belongsTo(Approval::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }
}
