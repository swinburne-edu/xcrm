<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 2.3: daily quota snapshots (trend + change detection for the hourly aggregation job) and Laravel's
 * database notifications (bell in the header; used by quota alerts here and SLA alerts in task 2.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quota_snapshots', function (Blueprint $table) {
            $table->id();
            $table->organizationId();
            $table->foreignId('cycle_id')->constrained('admission_cycles')->cascadeOnDelete();
            $table->foreignId('major_id')->constrained('majors')->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->unsignedInteger('quota_total')->default(0);
            $table->unsignedInteger('target')->default(0);
            $table->unsignedInteger('interested')->default(0);
            $table->unsignedInteger('submitted')->default(0);
            $table->unsignedInteger('eligible')->default(0);
            $table->unsignedInteger('admitted')->default(0);
            $table->unsignedInteger('confirmed')->default(0);
            $table->unsignedInteger('enrolled')->default(0);
            $table->unsignedInteger('secured')->default(0);
            $table->string('level', 10)->default('ok');
            $table->timestamps();
            $table->unique(['cycle_id', 'major_id', 'snapshot_date']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('quota_snapshots');
    }
};
