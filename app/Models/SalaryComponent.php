<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalaryComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'type',
        'taxable',
        'default_amount',
        'is_active',
    ];

    protected $casts = [
        'taxable' => 'boolean',
        'default_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function payslipItems()
    {
        return $this->hasMany(PayslipItem::class);
    }
}
