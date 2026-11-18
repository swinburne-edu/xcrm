<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * High schools and their contacts (docs/database.dbml §3). school_activities and
 * school_activity_* come with the activities module.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('high_schools', function (Blueprint $table) {
            $table->id();
            $table->organizationId();
            $table->string('code', 20)->nullable();
            $table->string('name');
            $table->string('type', 30)->nullable();
            $table->foreignId('province_id')->nullable()->constrained('provinces');
            $table->foreignId('ward_id')->nullable()->constrained('wards');
            $table->string('address', 500)->nullable();
            $table->foreignId('priority_area_id')->nullable()->constrained('priority_areas');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->integer('grade12_students')->nullable();
            $table->integer('grade12_classes')->nullable();
            $table->json('strong_combinations')->nullable();
            $table->char('tier', 1)->nullable();
            $table->string('partnership_status', 30)->default('not_contacted');
            $table->date('mou_signed_at')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->text('note')->nullable();
            $table->json('custom_data')->nullable();
            $table->auditColumns();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'code']);
            $table->index(['province_id', 'tier']);
        });

        Schema::create('school_contacts', function (Blueprint $table) {
            $table->id();
            $table->organizationId();
            $table->foreignId('high_school_id')->constrained('high_schools')->cascadeOnDelete();
            $table->string('full_name');
            $table->string('role', 30)->nullable();
            $table->string('homeroom_class', 20)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('zalo', 50)->nullable();
            $table->unsignedTinyInteger('relationship_level')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->text('note')->nullable();
            $table->json('custom_data')->nullable();
            $table->auditColumns();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_contacts');
        Schema::dropIfExists('high_schools');
    }
};
