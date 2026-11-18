<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Timeline entries (notes, calls, system events) on any record, tasks and appointments
 * (docs/database.dbml §10). Subjects are stored with their morph alias (applicant, admission_case…).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->organizationId();
            $table->string('subject_type', 100);
            $table->unsignedBigInteger('subject_id');
            $table->string('type', 30);
            $table->string('direction', 5)->nullable();
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->foreignId('call_result_id')->nullable()->constrained('call_results');
            $table->integer('duration_sec')->nullable();
            $table->string('recording_url', 500)->nullable();
            $table->json('meta')->nullable();
            $table->dateTime('occurred_at');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id', 'occurred_at']);
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->organizationId();
            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('title');
            $table->string('type', 30)->default('other');
            $table->string('priority', 10)->default('normal');
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('due_at')->nullable();
            $table->string('status', 20)->default('todo');
            $table->dateTime('completed_at')->nullable();
            $table->auditColumns();
            $table->timestamps();
            $table->index(['assignee_id', 'status', 'due_at']);
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->organizationId();
            $table->foreignId('case_id')->constrained('admission_cases')->cascadeOnDelete();
            $table->string('type', 30);
            $table->dateTime('start_at');
            $table->dateTime('end_at')->nullable();
            $table->string('location')->nullable();
            $table->foreignId('host_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('attendance', 20)->default('scheduled');
            $table->text('result')->nullable();
            $table->auditColumns();
            $table->timestamps();
            $table->index(['case_id', 'start_at']);
            $table->index(['host_id', 'start_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('activities');
    }
};
