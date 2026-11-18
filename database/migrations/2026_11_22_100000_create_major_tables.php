<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Faculties, campuses, majors and the per-cycle configuration of a major: quota, subject
 * combinations and admission methods (docs/database.dbml §4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faculties', function (Blueprint $table) {
            $table->id();
            $table->organizationId();
            $table->string('code', 20);
            $table->string('name');
            $table->unique(['organization_id', 'code']);
        });

        Schema::create('campuses', function (Blueprint $table) {
            $table->id();
            $table->organizationId();
            $table->string('code', 20);
            $table->string('name');
            $table->foreignId('province_id')->nullable()->constrained('provinces');
            $table->string('address', 500)->nullable();
            $table->unique(['organization_id', 'code']);
        });

        Schema::create('majors', function (Blueprint $table) {
            $table->id();
            $table->organizationId();
            $table->char('moet_code', 7)->nullable();
            $table->string('admission_code', 20);
            $table->string('name');
            $table->foreignId('faculty_id')->nullable()->constrained('faculties');
            $table->foreignId('campus_id')->nullable()->constrained('campuses');
            $table->string('program_type', 30)->default('standard');
            $table->string('level', 30)->default('university');
            $table->decimal('duration_years', 3, 1)->nullable();
            $table->text('description')->nullable();
            $table->text('career_info')->nullable();
            $table->json('faq')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('custom_data')->nullable();
            $table->auditColumns();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'admission_code']);
        });

        Schema::create('major_quotas', function (Blueprint $table) {
            $table->id();
            $table->organizationId();
            $table->foreignId('major_id')->constrained('majors')->cascadeOnDelete();
            $table->foreignId('cycle_id')->constrained('admission_cycles');
            $table->integer('quota_total');
            $table->integer('quota_direct')->default(0);
            $table->integer('target_enrolled')->nullable();
            $table->decimal('tuition_fee', 15, 0)->nullable();
            $table->decimal('min_threshold', 5, 2)->nullable();
            $table->json('previous_cutoffs')->nullable();
            $table->text('scholarship_info')->nullable();
            $table->timestamps();
            $table->unique(['major_id', 'cycle_id']);
        });

        Schema::create('major_combinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('major_id')->constrained('majors')->cascadeOnDelete();
            $table->foreignId('combination_id')->constrained('subject_combinations');
            $table->foreignId('cycle_id')->constrained('admission_cycles');
            $table->unique(['major_id', 'combination_id', 'cycle_id']);
        });

        Schema::create('major_cycle_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('major_id')->constrained('majors')->cascadeOnDelete();
            $table->foreignId('cycle_method_id')->constrained('cycle_methods')->cascadeOnDelete();
            $table->unique(['major_id', 'cycle_method_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('major_cycle_methods');
        Schema::dropIfExists('major_combinations');
        Schema::dropIfExists('major_quotas');
        Schema::dropIfExists('majors');
        Schema::dropIfExists('campuses');
        Schema::dropIfExists('faculties');
    }
};
