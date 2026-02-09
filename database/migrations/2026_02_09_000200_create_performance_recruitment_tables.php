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
        Schema::create('kpi_okrs', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->unique();
            $table->string('title');
            $table->text('objective')->nullable();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('target_value', 15, 2)->nullable();
            $table->decimal('current_value', 15, 2)->nullable();
            $table->string('unit', 20)->nullable();
            $table->unsignedTinyInteger('weight')->default(0);
            $table->enum('status', ['draft', 'active', 'completed', 'on_hold'])->default('draft');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['period_start', 'period_end']);
        });

        Schema::create('appraisals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('score', 5, 2)->nullable();
            $table->string('rating', 20)->nullable();
            $table->enum('status', ['draft', 'in_review', 'completed'])->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['period_start', 'period_end']);
        });

        Schema::create('trainings', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->unique();
            $table->string('title');
            $table->string('provider')->nullable();
            $table->date('training_date')->nullable();
            $table->unsignedInteger('duration_hours')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->enum('status', ['planned', 'ongoing', 'completed', 'cancelled'])->default('planned');
            $table->boolean('mandatory')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['training_date', 'status']);
        });

        Schema::create('job_posts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->unique();
            $table->string('title');
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('employment_type', ['permanent', 'contract', 'internship', 'daily', 'freelance'])->nullable();
            $table->unsignedInteger('openings')->default(1);
            $table->date('posted_at')->nullable();
            $table->date('closes_at')->nullable();
            $table->enum('status', ['draft', 'published', 'closed'])->default('draft');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['status', 'posted_at']);
        });

        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->unique();
            $table->string('full_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->foreignId('job_post_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('stage', ['applied', 'screening', 'interview', 'offer', 'hired', 'rejected'])->default('applied');
            $table->string('source')->nullable();
            $table->date('applied_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['job_post_id', 'stage']);
            $table->index('email');
        });

        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('interviewer_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('interview_date');
            $table->time('interview_time')->nullable();
            $table->enum('mode', ['online', 'offline', 'phone'])->default('online');
            $table->string('location')->nullable();
            $table->enum('result', ['scheduled', 'passed', 'failed', 'cancelled'])->default('scheduled');
            $table->decimal('score', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['candidate_id', 'interview_date']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('module');
            $table->string('action');
            $table->string('severity', 20)->default('info');
            $table->string('actor_name')->nullable();
            $table->string('actor_email')->nullable();
            $table->string('subject')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->date('occurred_at');
            $table->text('notes')->nullable();
            $table->boolean('is_flagged')->default(false);
            $table->timestamps();

            $table->index(['module', 'occurred_at']);
            $table->index('severity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('interviews');
        Schema::dropIfExists('candidates');
        Schema::dropIfExists('job_posts');
        Schema::dropIfExists('trainings');
        Schema::dropIfExists('appraisals');
        Schema::dropIfExists('kpi_okrs');
    }
};

