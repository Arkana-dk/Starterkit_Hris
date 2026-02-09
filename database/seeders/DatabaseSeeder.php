<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeProfile;
use App\Models\JobLevel;
use App\Models\LeaveType;
use App\Models\Position;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkLocation;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (!filter_var(env('SEED_DEMO_DATA', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $company = Company::updateOrCreate(
            ['name' => 'Human Resource Suite'],
            [
                'legal_name' => 'PT Human Resource Suite Indonesia',
                'industry' => 'Human Capital & Technology',
                'tax_id' => '01.234.567.8-901.000',
                'email' => 'hello@hr-suite.id',
                'phone' => '+62 21 555 0100',
                'website' => 'https://hr-suite.id',
                'address_line1' => 'Jl. Jendral Sudirman No. 45',
                'city' => 'Jakarta Selatan',
                'province' => 'DKI Jakarta',
                'postal_code' => '12190',
                'country' => 'ID',
                'timezone' => 'Asia/Jakarta',
                'is_active' => true,
            ],
        );

        $branch = Branch::updateOrCreate(
            ['company_id' => $company->id, 'code' => 'HQ-JKT'],
            [
                'name' => 'Head Office Jakarta',
                'phone' => '+62 21 555 0101',
                'address_line1' => 'Jl. Jendral Sudirman No. 45',
                'city' => 'Jakarta Selatan',
                'province' => 'DKI Jakarta',
                'postal_code' => '12190',
                'country' => 'ID',
                'timezone' => 'Asia/Jakarta',
                'latitude' => -6.2000000,
                'longitude' => 106.8166660,
                'is_head_office' => true,
                'is_active' => true,
            ],
        );

        $workLocation = WorkLocation::updateOrCreate(
            ['company_id' => $company->id, 'name' => 'Kantor Pusat'],
            [
                'branch_id' => $branch->id,
                'address' => 'Lantai 10, Jl. Jendral Sudirman No. 45, Jakarta Selatan',
                'latitude' => -6.2000000,
                'longitude' => 106.8166660,
                'radius_meters' => 150,
                'is_active' => true,
            ],
        );

        $departments = [
            'HR' => Department::updateOrCreate(
                ['company_id' => $company->id, 'code' => 'HR'],
                [
                    'branch_id' => $branch->id,
                    'name' => 'Human Resources',
                    'description' => 'Talent management, employee relations, and compliance.',
                    'is_active' => true,
                ],
            ),
            'FIN' => Department::updateOrCreate(
                ['company_id' => $company->id, 'code' => 'FIN'],
                [
                    'branch_id' => $branch->id,
                    'name' => 'Finance',
                    'description' => 'Payroll, budgeting, and financial reporting.',
                    'is_active' => true,
                ],
            ),
            'OPS' => Department::updateOrCreate(
                ['company_id' => $company->id, 'code' => 'OPS'],
                [
                    'branch_id' => $branch->id,
                    'name' => 'Operations',
                    'description' => 'Day-to-day operations and workforce planning.',
                    'is_active' => true,
                ],
            ),
            'IT' => Department::updateOrCreate(
                ['company_id' => $company->id, 'code' => 'IT'],
                [
                    'branch_id' => $branch->id,
                    'name' => 'IT & Systems',
                    'description' => 'Infrastructure, security, and internal systems.',
                    'is_active' => true,
                ],
            ),
        ];

        $jobLevels = [
            'L1' => JobLevel::updateOrCreate(
                ['company_id' => $company->id, 'code' => 'L1'],
                [
                    'name' => 'Staff',
                    'rank' => 1,
                    'description' => 'Entry to mid-level staff.',
                ],
            ),
            'L2' => JobLevel::updateOrCreate(
                ['company_id' => $company->id, 'code' => 'L2'],
                [
                    'name' => 'Senior Staff',
                    'rank' => 2,
                    'description' => 'Senior individual contributor.',
                ],
            ),
            'L3' => JobLevel::updateOrCreate(
                ['company_id' => $company->id, 'code' => 'L3'],
                [
                    'name' => 'Supervisor',
                    'rank' => 3,
                    'description' => 'Supervises small teams.',
                ],
            ),
            'L4' => JobLevel::updateOrCreate(
                ['company_id' => $company->id, 'code' => 'L4'],
                [
                    'name' => 'Manager',
                    'rank' => 4,
                    'description' => 'Leads department initiatives.',
                ],
            ),
            'L5' => JobLevel::updateOrCreate(
                ['company_id' => $company->id, 'code' => 'L5'],
                [
                    'name' => 'Head',
                    'rank' => 5,
                    'description' => 'Strategic leadership level.',
                ],
            ),
        ];

        $positions = [
            'HR_HEAD' => Position::updateOrCreate(
                ['company_id' => $company->id, 'code' => 'HR-HEAD'],
                [
                    'department_id' => $departments['HR']->id,
                    'job_level_id' => $jobLevels['L5']->id,
                    'title' => 'Head of HR',
                    'description' => 'Leads HR strategy and governance.',
                    'is_leadership' => true,
                    'is_active' => true,
                ],
            ),
            'HR_MANAGER' => Position::updateOrCreate(
                ['company_id' => $company->id, 'code' => 'HR-MGR'],
                [
                    'department_id' => $departments['HR']->id,
                    'job_level_id' => $jobLevels['L4']->id,
                    'title' => 'HR Manager',
                    'description' => 'Manages HR operations and programs.',
                    'is_leadership' => true,
                    'is_active' => true,
                ],
            ),
            'HR_STAFF' => Position::updateOrCreate(
                ['company_id' => $company->id, 'code' => 'HR-STAFF'],
                [
                    'department_id' => $departments['HR']->id,
                    'job_level_id' => $jobLevels['L1']->id,
                    'title' => 'HR Staff',
                    'description' => 'Supports HR administration.',
                    'is_leadership' => false,
                    'is_active' => true,
                ],
            ),
            'OPS_STAFF' => Position::updateOrCreate(
                ['company_id' => $company->id, 'code' => 'OPS-STAFF'],
                [
                    'department_id' => $departments['OPS']->id,
                    'job_level_id' => $jobLevels['L1']->id,
                    'title' => 'Operations Staff',
                    'description' => 'Daily operations execution.',
                    'is_leadership' => false,
                    'is_active' => true,
                ],
            ),
        ];

        Shift::updateOrCreate(
            ['company_id' => $company->id, 'name' => 'Shift Pagi'],
            [
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
                'break_minutes' => 60,
                'grace_minutes' => 10,
                'is_overnight' => false,
                'is_active' => true,
            ],
        );

        Shift::updateOrCreate(
            ['company_id' => $company->id, 'name' => 'Shift Malam'],
            [
                'start_time' => '21:00:00',
                'end_time' => '06:00:00',
                'break_minutes' => 60,
                'grace_minutes' => 10,
                'is_overnight' => true,
                'is_active' => true,
            ],
        );

        LeaveType::updateOrCreate(
            ['company_id' => $company->id, 'code' => 'AL'],
            [
                'name' => 'Cuti Tahunan',
                'category' => 'annual',
                'default_allocation' => 12,
                'carry_over_limit' => 6,
                'requires_attachment' => false,
                'requires_approval' => true,
                'paid' => true,
                'description' => 'Cuti tahunan sesuai ketentuan perusahaan.',
                'is_active' => true,
            ],
        );

        LeaveType::updateOrCreate(
            ['company_id' => $company->id, 'code' => 'SL'],
            [
                'name' => 'Cuti Sakit',
                'category' => 'sick',
                'default_allocation' => 12,
                'carry_over_limit' => 0,
                'requires_attachment' => true,
                'requires_approval' => true,
                'paid' => true,
                'description' => 'Cuti sakit dengan bukti medis.',
                'is_active' => true,
            ],
        );

        LeaveType::updateOrCreate(
            ['company_id' => $company->id, 'code' => 'UP'],
            [
                'name' => 'Cuti Tanpa Bayar',
                'category' => 'unpaid',
                'default_allocation' => 0,
                'carry_over_limit' => 0,
                'requires_attachment' => false,
                'requires_approval' => true,
                'paid' => false,
                'description' => 'Cuti di luar alokasi tahunan.',
                'is_active' => true,
            ],
        );

        LeaveType::updateOrCreate(
            ['company_id' => $company->id, 'code' => 'ML'],
            [
                'name' => 'Cuti Melahirkan',
                'category' => 'maternity',
                'default_allocation' => 90,
                'carry_over_limit' => 0,
                'requires_attachment' => true,
                'requires_approval' => true,
                'paid' => true,
                'description' => 'Cuti melahirkan sesuai peraturan.',
                'is_active' => true,
            ],
        );

        LeaveType::updateOrCreate(
            ['company_id' => $company->id, 'code' => 'PL'],
            [
                'name' => 'Cuti Ayah',
                'category' => 'paternity',
                'default_allocation' => 3,
                'carry_over_limit' => 0,
                'requires_attachment' => true,
                'requires_approval' => true,
                'paid' => true,
                'description' => 'Cuti ayah sesuai peraturan.',
                'is_active' => true,
            ],
        );

        // Create SuperAdmin User
        $superAdmin = User::updateOrCreate(
            ['email' => 'superadmin@hr.com'],
            [
                'name' => 'Super Administrator',
                'password' => Hash::make('password'),
                'role' => 'superadmin',
                'email_verified_at' => now(),
            ],
        );

        // Create Admin User
        $admin = User::updateOrCreate(
            ['email' => 'admin@hr.com'],
            [
                'name' => 'HR Administrator',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ],
        );

        // Create Employee User
        $employee = User::updateOrCreate(
            ['email' => 'employee@hr.com'],
            [
                'name' => 'John Employee',
                'password' => Hash::make('password'),
                'role' => 'employee',
                'email_verified_at' => now(),
            ],
        );

        $superEmployee = Employee::updateOrCreate(
            ['user_id' => $superAdmin->id],
            [
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'department_id' => $departments['HR']->id,
                'position_id' => $positions['HR_HEAD']->id,
                'job_level_id' => $jobLevels['L5']->id,
                'employee_code' => 'EMP-0001',
                'employment_status' => 'active',
                'employment_type' => 'permanent',
                'join_date' => now()->subYears(6)->toDateString(),
                'confirmation_date' => now()->subYears(5)->toDateString(),
                'work_email' => $superAdmin->email,
                'work_phone' => '+62 21 555 0102',
                'office_location' => $workLocation->name,
                'is_active' => true,
            ],
        );

        $adminEmployee = Employee::updateOrCreate(
            ['user_id' => $admin->id],
            [
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'department_id' => $departments['HR']->id,
                'position_id' => $positions['HR_MANAGER']->id,
                'job_level_id' => $jobLevels['L4']->id,
                'manager_id' => $superEmployee->id,
                'employee_code' => 'EMP-0002',
                'employment_status' => 'active',
                'employment_type' => 'permanent',
                'join_date' => now()->subYears(4)->toDateString(),
                'confirmation_date' => now()->subYears(3)->toDateString(),
                'work_email' => $admin->email,
                'work_phone' => '+62 21 555 0103',
                'office_location' => $workLocation->name,
                'is_active' => true,
            ],
        );

        $employeeEmployee = Employee::updateOrCreate(
            ['user_id' => $employee->id],
            [
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'department_id' => $departments['OPS']->id,
                'position_id' => $positions['OPS_STAFF']->id,
                'job_level_id' => $jobLevels['L1']->id,
                'manager_id' => $adminEmployee->id,
                'employee_code' => 'EMP-0003',
                'employment_status' => 'active',
                'employment_type' => 'permanent',
                'join_date' => now()->subYears(2)->toDateString(),
                'confirmation_date' => now()->subYears(1)->toDateString(),
                'work_email' => $employee->email,
                'work_phone' => '+62 21 555 0104',
                'office_location' => $workLocation->name,
                'is_active' => true,
            ],
        );

        EmployeeProfile::updateOrCreate(
            ['employee_id' => $superEmployee->id],
            [
                'nik' => '3171010101010001',
                'kk_number' => '3171010101010001',
                'npwp' => '09.123.456.7-890.000',
                'bpjs_kes' => '0123456789012',
                'bpjs_tk' => '0123456789012',
                'gender' => 'male',
                'birth_place' => 'Jakarta',
                'birth_date' => now()->subYears(38)->toDateString(),
                'marital_status' => 'married',
                'religion' => 'Islam',
                'address_line1' => 'Jl. Kebayoran Baru No. 15',
                'city' => 'Jakarta Selatan',
                'province' => 'DKI Jakarta',
                'postal_code' => '12160',
                'emergency_contact_name' => 'Siti Rahma',
                'emergency_contact_relation' => 'Spouse',
                'emergency_contact_phone' => '+62 812 0000 0001',
                'bank_name' => 'BCA',
                'bank_account_name' => 'Super Administrator',
                'bank_account_number' => '1234567890',
            ],
        );

        EmployeeProfile::updateOrCreate(
            ['employee_id' => $adminEmployee->id],
            [
                'nik' => '3171010101010002',
                'kk_number' => '3171010101010002',
                'npwp' => '09.123.456.7-890.001',
                'bpjs_kes' => '0123456789013',
                'bpjs_tk' => '0123456789013',
                'gender' => 'female',
                'birth_place' => 'Bandung',
                'birth_date' => now()->subYears(32)->toDateString(),
                'marital_status' => 'single',
                'religion' => 'Islam',
                'address_line1' => 'Jl. Ciumbuleuit No. 7',
                'city' => 'Bandung',
                'province' => 'Jawa Barat',
                'postal_code' => '40142',
                'emergency_contact_name' => 'Rahmat Pratama',
                'emergency_contact_relation' => 'Brother',
                'emergency_contact_phone' => '+62 812 0000 0002',
                'bank_name' => 'Mandiri',
                'bank_account_name' => 'HR Administrator',
                'bank_account_number' => '2345678901',
            ],
        );

        EmployeeProfile::updateOrCreate(
            ['employee_id' => $employeeEmployee->id],
            [
                'nik' => '3171010101010003',
                'kk_number' => '3171010101010003',
                'npwp' => '09.123.456.7-890.002',
                'bpjs_kes' => '0123456789014',
                'bpjs_tk' => '0123456789014',
                'gender' => 'male',
                'birth_place' => 'Surabaya',
                'birth_date' => now()->subYears(27)->toDateString(),
                'marital_status' => 'single',
                'religion' => 'Islam',
                'address_line1' => 'Jl. Darmo Permai No. 21',
                'city' => 'Surabaya',
                'province' => 'Jawa Timur',
                'postal_code' => '60241',
                'emergency_contact_name' => 'Rina Wulandari',
                'emergency_contact_relation' => 'Sibling',
                'emergency_contact_phone' => '+62 812 0000 0003',
                'bank_name' => 'BRI',
                'bank_account_name' => 'John Employee',
                'bank_account_number' => '3456789012',
            ],
        );
    }
}
