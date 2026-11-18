<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing campaigns of a cycle and what they target (majors, provinces, high schools).
 * campaign_costs (daily costs) is P1 and not created yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->organizationId();
            $table->foreignId('cycle_id')->constrained('admission_cycles');
            $table->foreignId('parent_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->string('type', 30)->nullable();
            $table->string('channel', 30)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('budget', 15, 0)->nullable();
            $table->decimal('actual_cost', 15, 0)->nullable();
            $table->integer('target_leads')->nullable();
            $table->integer('target_applications')->nullable();
            $table->integer('target_enrolled')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->string('landing_url', 500)->nullable();
            $table->text('description')->nullable();
            $table->json('custom_data')->nullable();
            $table->auditColumns();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'code']);
            $table->index(['cycle_id', 'status']);
        });

        Schema::create('campaign_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->string('targetable_type', 100);
            $table->unsignedBigInteger('targetable_id');
            $table->unique(['campaign_id', 'targetable_type', 'targetable_id'], 'campaign_targets_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_targets');
        Schema::dropIfExists('campaigns');
    }
};
