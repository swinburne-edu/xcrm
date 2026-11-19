<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Simple automation (task 3.2): a trigger, conditions and actions per workflow, and one run row per execution. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->organizationId();
            $table->string('name');
            $table->string('object', 50);
            $table->json('trigger');
            $table->json('conditions')->nullable();
            $table->json('actions');
            $table->boolean('is_active')->default(false);
            $table->auditColumns();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'object', 'is_active']);
        });

        Schema::create('workflow_runs', function (Blueprint $table) {
            $table->id();
            $table->organizationId();
            $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();
            $table->string('subject_type', 100);
            $table->unsignedBigInteger('subject_id');
            $table->string('status', 20);
            $table->json('log')->nullable();
            $table->dateTime('ran_at');
            $table->index(['workflow_id', 'subject_type', 'subject_id']);
            $table->index(['workflow_id', 'ran_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_runs');
        Schema::dropIfExists('workflows');
    }
};
