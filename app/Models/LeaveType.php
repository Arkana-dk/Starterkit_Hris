<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'category',
        'default_allocation',
        'carry_over_limit',
        'requires_attachment',
        'requires_approval',
        'paid',
        'description',
        'is_active',
    ];

    protected $casts = [
        'default_allocation' => 'integer',
        'carry_over_limit' => 'integer',
        'requires_attachment' => 'boolean',
        'requires_approval' => 'boolean',
        'paid' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function balances()
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function requests()
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
