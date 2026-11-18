<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lead intake (M7): public web forms (embedded on the university website, posting to /api/public/leads)
 * and the Excel import jobs. case_touches.form_id already exists; it gets its foreign key here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            $table->organizationId();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->foreignId('source_id')->nullable()->constrained('lead_sources')->nullOnDelete();
            $table->json('fields');
            $table->text('success_message')->nullable();
            $table->boolean('is_active')->default(true);
            $table->auditColumns();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('case_touches', function (Blueprint $table) {
            $table->foreign('form_id')->references('id')->on('forms')->nullOnDelete();
        });

        Schema::create('import_jobs', function (Blueprint $table) {
            $table->id();
            $table->organizationId();
            $table->string('object', 50);
            $table->string('file_path', 500);
            $table->string('original_name')->nullable();
            $table->json('mapping')->nullable();
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('success_rows')->default(0);
            $table->unsignedInteger('duplicate_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);
            $table->json('errors')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_jobs');

        Schema::table('case_touches', function (Blueprint $table) {
            $table->dropForeign(['form_id']);
        });

        Schema::dropIfExists('forms');
    }
};
