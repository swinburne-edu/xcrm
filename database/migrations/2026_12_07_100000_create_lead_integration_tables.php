<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 2.7: lead ads webhooks (Facebook Lead Ads, TikTok). One integration = one public webhook URL (by uuid) with its
 * secrets, default source / campaign and optional field mapping. Every lead that arrives is logged once
 * (unique integration_id + external_id) so retries from the ad platform never create it twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_integrations', function (Blueprint $table) {
            $table->id();
            $table->organizationId();
            $table->uuid('uuid')->unique();
            $table->string('provider', 20);
            $table->string('name');
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->foreignId('source_id')->nullable()->constrained('lead_sources')->nullOnDelete();
            $table->text('secret')->nullable();
            $table->text('verify_token')->nullable();
            $table->text('access_token')->nullable();
            $table->json('field_map')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_received_at')->nullable();
            $table->auditColumns();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'provider']);
        });

        Schema::create('lead_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained('lead_integrations')->cascadeOnDelete();
            $table->string('external_id', 100)->nullable();
            $table->string('status', 20);
            $table->text('error')->nullable();
            $table->json('payload')->nullable();
            $table->foreignId('applicant_id')->nullable()->constrained('applicants')->nullOnDelete();
            $table->timestamps();
            $table->unique(['integration_id', 'external_id']);
            $table->index(['integration_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_webhook_events');
        Schema::dropIfExists('lead_integrations');
    }
};
