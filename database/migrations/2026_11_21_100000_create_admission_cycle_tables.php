<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admission cycles, their rounds and the admission methods used in each cycle
 * (docs/database.dbml §5). Everything about admissions hangs off a cycle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_cycles', function (Blueprint $table) {
            $table->id();
            $table->organizationId();
            $table->string('name');
            $table->smallInteger('year');
            $table->string('level', 30)->default('university');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('preparing');
            $table->integer('target_enrolled')->nullable();
            $table->string('regulation_ref')->nullable();
            $table->boolean('is_current')->default(false);
            $table->auditColumns();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'year', 'level']);
        });

        Schema::create('admission_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('admission_cycles')->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 30)->nullable();
            $table->dateTime('apply_start')->nullable();
            $table->dateTime('apply_end')->nullable();
            $table->date('result_date')->nullable();
            $table->dateTime('confirm_deadline')->nullable();
            $table->integer('sort_order')->default(0);
        });

        Schema::create('cycle_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('admission_cycles')->cascadeOnDelete();
            $table->foreignId('method_id')->constrained('admission_methods');
            $table->string('display_name')->nullable();
            $table->boolean('is_direct')->default(false);
            $table->json('score_formula')->nullable();
            $table->json('conditions')->nullable();
            $table->json('required_documents')->nullable();
            $table->unique(['cycle_id', 'method_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cycle_methods');
        Schema::dropIfExists('admission_rounds');
        Schema::dropIfExists('admission_cycles');
    }
};
