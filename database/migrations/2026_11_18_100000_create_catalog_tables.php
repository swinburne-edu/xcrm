<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * System catalogs (docs/database.dbml §2). Shared by all organizations:
 * no organization_id, no audit columns, no soft deletes (deactivate with is_active).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->string('type', 20)->nullable();
            $table->boolean('is_active')->default(true);
        });

        Schema::create('wards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained('provinces');
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->string('type', 20)->nullable();
            $table->boolean('is_active')->default(true);
        });

        Schema::create('ward_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('old_province_name')->nullable();
            $table->string('old_district_name')->nullable();
            $table->string('old_ward_name')->nullable();
            $table->foreignId('new_ward_id')->nullable()->constrained('wards');
        });

        Schema::create('priority_areas', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 100)->nullable();
            $table->decimal('bonus_score', 4, 2)->default(0);
        });

        Schema::create('priority_groups', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name')->nullable();
            $table->decimal('bonus_score', 4, 2)->default(0);
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100)->nullable();
            $table->boolean('is_core')->default(false);
        });

        Schema::create('subject_combinations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name')->nullable();
            $table->boolean('is_active')->default(true);
        });

        Schema::create('combination_subjects', function (Blueprint $table) {
            $table->foreignId('combination_id')->constrained('subject_combinations')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects');
            $table->decimal('weight', 4, 2)->default(1);
            $table->primary(['combination_id', 'subject_id']);
        });

        Schema::create('admission_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name')->nullable();
            $table->string('score_source', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_methods');
        Schema::dropIfExists('combination_subjects');
        Schema::dropIfExists('subject_combinations');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('priority_groups');
        Schema::dropIfExists('priority_areas');
        Schema::dropIfExists('ward_mappings');
        Schema::dropIfExists('wards');
        Schema::dropIfExists('provinces');
    }
};
