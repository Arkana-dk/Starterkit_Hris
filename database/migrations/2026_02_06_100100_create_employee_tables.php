<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('job_level_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('employee_code')->unique();
            $table->enum('employment_status', [
                'active',
                'probation',
                'contract',
                'resign',
                'terminated',
            ])->default('active');
            $table->enum('employment_type', [
                'permanent',
                'contract',
                'internship',
                'daily',
                'freelance',
            ])->default('permanent');
            $table->date('join_date');
            $table->date('confirmation_date')->nullable();
            $table->date('resign_date')->nullable();
            $table->string('work_email')->nullable();
            $table->string('work_phone')->nullable();
            $table->string('office_location')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('user_id');
            $table->index(['company_id', 'department_id', 'position_id']);
        });

        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('nik', 32)->nullable();
            $table->string('kk_number', 32)->nullable();
            $table->string('npwp', 32)->nullable();
            $table->string('bpjs_kes', 32)->nullable();
            $table->string('bpjs_tk', 32)->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->enum('marital_status', ['single', 'married', 'divorced', 'widowed'])->nullable();
            $table->string('religion')->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_relation')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('photo_path')->nullable();
            $table->timestamps();

            $table->unique('employee_id');
            $table->index('nik');
        });

        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('number')->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('file_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'type']);
        });

        Schema::create('employee_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['permanent', 'contract', 'internship', 'probation']);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('base_salary', 15, 2)->nullable();
            $table->enum('status', ['active', 'expired', 'terminated'])->default('active');
            $table->date('signed_at')->nullable();
            $table->string('file_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_contracts');
        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('employee_profiles');
        Schema::dropIfExists('employees');
    }
};
