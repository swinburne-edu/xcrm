<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pipelines with their stages, and the admission cases (one per applicant per cycle) that move through them
 * (docs/database.dbml §7). school_activity_id / form_id are plain columns until those tables exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipelines', function (Blueprint $table) {
            $table->id();
            $table->organizationId();
            $table->string('name');
            $table->string('level', 30)->default('university');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('pipeline_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipeline_id')->constrained('pipelines')->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->string('color', 20)->nullable();
            $table->integer('sort_order')->default(0);
            $table->string('type', 10)->default('open');
            $table->unsignedTinyInteger('probability')->default(0);
            $table->integer('sla_hours')->nullable();
            $table->json('required_fields')->nullable();
            $table->unique(['pipeline_id', 'code']);
        });

        Schema::create('admission_cases', function (Blueprint $table) {
            $table->id();
            $table->organizationId();
            $table->uuid('uuid')->unique();
            $table->foreignId('applicant_id')->constrained('applicants');
            $table->foreignId('cycle_id')->constrained('admission_cycles');
            $table->foreignId('pipeline_id')->constrained('pipelines');
            $table->foreignId('stage_id')->constrained('pipeline_stages');
            $table->string('status', 10)->default('open');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('source_id')->nullable()->constrained('lead_sources');
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns');
            $table->unsignedBigInteger('school_activity_id')->nullable();
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 100)->nullable();
            $table->string('utm_content', 100)->nullable();
            $table->foreignId('primary_major_id')->nullable()->constrained('majors');
            $table->string('temperature', 10)->nullable();
            $table->integer('score')->default(0);
            $table->unsignedTinyInteger('our_preference_rank')->nullable();
            $table->dateTime('assigned_at')->nullable();
            $table->dateTime('first_contacted_at')->nullable();
            $table->dateTime('last_activity_at')->nullable();
            $table->dateTime('next_action_at')->nullable();
            $table->foreignId('lost_reason_id')->nullable()->constrained('lost_reasons');
            $table->text('lost_note')->nullable();
            $table->foreignId('enrolled_major_id')->nullable()->constrained('majors');
            $table->dateTime('enrolled_at')->nullable();
            $table->json('custom_data')->nullable();
            $table->auditColumns();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['applicant_id', 'cycle_id']);
            $table->index(['cycle_id', 'stage_id', 'owner_id']);
            $table->index(['cycle_id', 'campaign_id']);
            $table->index(['cycle_id', 'source_id']);
            $table->index('next_action_at');
        });

        Schema::create('case_stage_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('admission_cases')->cascadeOnDelete();
            $table->foreignId('from_stage_id')->nullable()->constrained('pipeline_stages');
            $table->foreignId('to_stage_id')->constrained('pipeline_stages');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('hours_in_previous', 10, 2)->nullable();
            $table->dateTime('changed_at');
        });

        Schema::create('case_touches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('admission_cases')->cascadeOnDelete();
            $table->foreignId('source_id')->nullable()->constrained('lead_sources');
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns');
            $table->unsignedBigInteger('school_activity_id')->nullable();
            $table->unsignedBigInteger('form_id')->nullable();
            $table->json('utm')->nullable();
            $table->json('payload')->nullable();
            $table->dateTime('touched_at');
        });

        Schema::create('case_interested_majors', function (Blueprint $table) {
            $table->foreignId('case_id')->constrained('admission_cases')->cascadeOnDelete();
            $table->foreignId('major_id')->constrained('majors');
            $table->unsignedTinyInteger('priority')->nullable();
            $table->primary(['case_id', 'major_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_interested_majors');
        Schema::dropIfExists('case_touches');
        Schema::dropIfExists('case_stage_histories');
        Schema::dropIfExists('admission_cases');
        Schema::dropIfExists('pipeline_stages');
        Schema::dropIfExists('pipelines');
    }
};
