<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Call centre (task 3.7): the webhook endpoints of the telephone provider and one row per call. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_integrations', function (Blueprint $table) {
            $table->id();
            $table->organizationId();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->text('secret');
            $table->boolean('is_active')->default(true);
            $table->dateTime('last_received_at')->nullable();
            $table->auditColumns();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('call_logs', function (Blueprint $table) {
            $table->id();
            $table->organizationId();
            $table->foreignId('integration_id')->nullable()->constrained('call_integrations')->nullOnDelete();
            $table->string('external_id', 100);
            $table->string('direction', 3);
            $table->string('from_number', 40)->nullable();
            $table->string('to_number', 40)->nullable();
            $table->string('phone_normalized', 20)->nullable()->index();
            $table->foreignId('applicant_id')->nullable()->constrained('applicants')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20);
            $table->dateTime('started_at')->nullable();
            $table->dateTime('answered_at')->nullable();
            $table->unsignedInteger('duration_sec')->nullable();
            $table->string('recording_url', 500)->nullable();
            $table->text('error')->nullable();
            $table->unsignedBigInteger('activity_id')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'external_id']);
            $table->index(['direction', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_logs');
        Schema::dropIfExists('call_integrations');
    }
};
