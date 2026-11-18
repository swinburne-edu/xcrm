<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 2.1: activities at (or about) a high school, their staff and the students who checked in by QR (flow F2).
 * admission_cases.school_activity_id and case_touches.school_activity_id already exist as plain columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_activities', function (Blueprint $table) {
            $table->id();
            $table->organizationId();
            $table->foreignId('cycle_id')->constrained('admission_cycles');
            $table->foreignId('high_school_id')->nullable()->constrained('high_schools')->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->string('type', 30);
            $table->string('title');
            $table->dateTime('start_at')->nullable();
            $table->dateTime('end_at')->nullable();
            $table->string('location')->nullable();
            $table->string('status', 20)->default('planned');
            $table->unsignedInteger('expected_attendees')->nullable();
            $table->unsignedInteger('actual_attendees')->nullable();
            $table->decimal('cost', 15, 0)->nullable();
            $table->string('checkin_token', 64)->unique();
            $table->text('result_note')->nullable();
            $table->auditColumns();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['cycle_id', 'start_at']);
            $table->index(['high_school_id', 'cycle_id']);
        });

        Schema::create('school_activity_user', function (Blueprint $table) {
            $table->foreignId('school_activity_id')->constrained('school_activities')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 30)->default('member');
            $table->primary(['school_activity_id', 'user_id']);
        });

        Schema::create('activity_attendees', function (Blueprint $table) {
            $table->id();
            $table->organizationId();
            $table->foreignId('school_activity_id')->constrained('school_activities')->cascadeOnDelete();
            $table->foreignId('applicant_id')->constrained('applicants');
            $table->dateTime('checked_in_at')->nullable();
            $table->json('form_data')->nullable();
            $table->timestamps();
            $table->unique(['school_activity_id', 'applicant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_attendees');
        Schema::dropIfExists('school_activity_user');
        Schema::dropIfExists('school_activities');
    }
};
