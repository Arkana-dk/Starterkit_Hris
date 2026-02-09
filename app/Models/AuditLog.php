<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'module',
        'action',
        'severity',
        'actor_name',
        'actor_email',
        'subject',
        'ip_address',
        'occurred_at',
        'notes',
        'is_flagged',
    ];

    protected $casts = [
        'occurred_at' => 'date',
        'is_flagged' => 'boolean',
    ];
}

