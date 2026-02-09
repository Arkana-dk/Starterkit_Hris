<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'nik',
        'kk_number',
        'npwp',
        'bpjs_kes',
        'bpjs_tk',
        'gender',
        'birth_place',
        'birth_date',
        'marital_status',
        'religion',
        'address_line1',
        'address_line2',
        'city',
        'province',
        'postal_code',
        'emergency_contact_name',
        'emergency_contact_relation',
        'emergency_contact_phone',
        'bank_name',
        'bank_account_name',
        'bank_account_number',
        'photo_path',
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
