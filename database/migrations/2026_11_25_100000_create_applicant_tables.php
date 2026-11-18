<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Applicants (candidates), their guardians, scores and certificates (docs/database.dbml §7).
 * Documents (medialibrary) and cases come with later tasks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicants', function (Blueprint $table) {
            $table->id();
            $table->organizationId();
            $table->uuid('uuid')->unique();
            $table->string('code', 20);
            $table->string('full_name');
            $table->string('gender', 10)->nullable();
            $table->date('dob')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('phone_normalized', 15)->nullable();
            $table->string('email')->nullable();
            $table->text('citizen_id')->nullable();
            $table->char('citizen_id_hash', 64)->nullable();
            $table->string('zalo_id', 100)->nullable();
            $table->string('facebook_id', 100)->nullable();
            $table->foreignId('province_id')->nullable()->constrained('provinces');
            $table->foreignId('ward_id')->nullable()->constrained('wards');
            $table->string('address', 500)->nullable();
            $table->foreignId('high_school_id')->nullable()->constrained('high_schools');
            $table->string('class_name', 20)->nullable();
            $table->unsignedTinyInteger('current_grade')->nullable();
            $table->smallInteger('graduation_year')->nullable();
            $table->foreignId('priority_area_id')->nullable()->constrained('priority_areas');
            $table->foreignId('priority_group_id')->nullable()->constrained('priority_groups');
            $table->json('interests')->nullable();
            $table->foreignId('first_source_id')->nullable()->constrained('lead_sources');
            $table->foreignId('first_campaign_id')->nullable()->constrained('campaigns');
            $table->foreignId('referred_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('consent_marketing')->default(false);
            $table->dateTime('consent_at')->nullable();
            $table->foreignId('merged_into_id')->nullable()->constrained('applicants');
            $table->json('custom_data')->nullable();
            $table->auditColumns();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'phone_normalized']);
            $table->index(['organization_id', 'email']);
            $table->index(['organization_id', 'citizen_id_hash']);
            $table->index('graduation_year');
        });

        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->organizationId();
            $table->string('full_name');
            $table->string('phone', 20)->nullable();
            $table->string('phone_normalized', 15)->nullable();
            $table->string('email')->nullable();
            $table->string('zalo_id', 100)->nullable();
            $table->string('occupation')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'phone_normalized']);
        });

        Schema::create('applicant_guardian', function (Blueprint $table) {
            $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();
            $table->foreignId('guardian_id')->constrained('guardians')->cascadeOnDelete();
            $table->string('relationship', 20)->nullable();
            $table->boolean('is_decision_maker')->default(false);
            $table->boolean('is_primary_contact')->default(false);
            $table->primary(['applicant_id', 'guardian_id']);
        });

        Schema::create('applicant_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();
            $table->string('score_type', 30);
            $table->foreignId('subject_id')->nullable()->constrained('subjects');
            $table->string('term', 10)->nullable();
            $table->string('exam_name', 100)->nullable();
            $table->decimal('score', 6, 2)->nullable();
            $table->decimal('max_score', 6, 2)->default(10);
            $table->smallInteger('year')->nullable();
            $table->boolean('verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['applicant_id', 'score_type', 'subject_id', 'term', 'exam_name'], 'applicant_scores_unique');
        });

        Schema::create('applicant_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('score', 20)->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->boolean('verified')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_certificates');
        Schema::dropIfExists('applicant_scores');
        Schema::dropIfExists('applicant_guardian');
        Schema::dropIfExists('guardians');
        Schema::dropIfExists('applicants');
    }
};
