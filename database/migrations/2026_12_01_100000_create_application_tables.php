<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 2.2: applications (nguyện vọng xét tuyển, docs/database.dbml) and applicant_documents (files via medialibrary).
 * applications has no soft delete: only drafts are deleted, so the unique (case, round, order) key stays reusable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->organizationId();
            $table->foreignId('case_id')->constrained('admission_cases')->cascadeOnDelete();
            $table->foreignId('major_id')->constrained('majors');
            $table->foreignId('cycle_method_id')->constrained('cycle_methods');
            $table->foreignId('combination_id')->nullable()->constrained('subject_combinations');
            $table->foreignId('round_id')->constrained('admission_rounds');
            $table->unsignedTinyInteger('preference_order');
            $table->decimal('raw_score', 5, 2)->nullable();
            $table->decimal('priority_bonus', 5, 2)->nullable();
            $table->decimal('encourage_bonus', 5, 2)->nullable();
            $table->decimal('final_score', 5, 2)->nullable();
            $table->json('score_breakdown')->nullable();
            $table->string('status', 30)->default('draft');
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('moet_synced_at')->nullable();
            $table->auditColumns();
            $table->timestamps();
            $table->unique(['case_id', 'round_id', 'preference_order']);
            $table->index(['major_id', 'round_id', 'status']);
        });

        Schema::create('applicant_documents', function (Blueprint $table) {
            $table->id();
            $table->organizationId();
            $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();
            $table->foreignId('case_id')->nullable()->constrained('admission_cases')->nullOnDelete();
            $table->string('doc_type', 30);
            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->string('note', 500)->nullable();
            $table->auditColumns();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['applicant_id', 'doc_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_documents');
        Schema::dropIfExists('applications');
    }
};
