<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'legal_name',
        'industry',
        'tax_id',
        'email',
        'phone',
        'website',
        'address_line1',
        'address_line2',
        'city',
        'province',
        'postal_code',
        'country',
        'timezone',
        'logo_path',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }

    public function departments()
    {
        return $this->hasMany(Department::class);
    }

    public function jobLevels()
    {
        return $this->hasMany(JobLevel::class);
    }

    public function positions()
    {
        return $this->hasMany(Position::class);
    }

    public function workLocations()
    {
        return $this->hasMany(WorkLocation::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function shifts()
    {
        return $this->hasMany(Shift::class);
    }

    public function leaveTypes()
    {
        return $this->hasMany(LeaveType::class);
    }

    public function salaryComponents()
    {
        return $this->hasMany(SalaryComponent::class);
    }

    public function payrollPeriods()
    {
        return $this->hasMany(PayrollPeriod::class);
    }
}
