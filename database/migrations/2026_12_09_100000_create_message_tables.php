<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Multi-channel messaging (task 3.1): reusable templates and one log row per message sent. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->organizationId();
            $table->string('channel', 20);
            $table->string('name');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('provider_template_id', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->auditColumns();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'channel']);
        });

        Schema::create('message_logs', function (Blueprint $table) {
            $table->id();
            $table->organizationId();
            $table->foreignId('template_id')->nullable()->constrained('message_templates')->nullOnDelete();
            $table->uuid('batch_id')->nullable()->index();
            $table->string('channel', 20);
            $table->string('recipient')->nullable();
            $table->foreignId('applicant_id')->nullable()->constrained('applicants')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->string('status', 20)->default('queued');
            $table->string('provider_message_id', 100)->nullable();
            $table->text('error')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
            $table->index(['applicant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_logs');
        Schema::dropIfExists('message_templates');
    }
};
