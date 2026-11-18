<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 2.4: assignment rules (who gets a new case) and the "SLA alert already sent" mark on cases.
 * Recall and SLA settings live in organizations.settings.assignment (no table needed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_rules', function (Blueprint $table) {
            $table->id();
            $table->organizationId();
            $table->string('name');
            $table->integer('priority')->default(100);
            $table->json('conditions')->nullable();
            $table->string('strategy', 20);
            $table->json('user_ids')->nullable();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->auditColumns();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'is_active', 'priority']);
        });

        Schema::table('admission_cases', function (Blueprint $table) {
            $table->dateTime('sla_alerted_at')->nullable()->after('next_action_at');
        });
    }

    public function down(): void
    {
        Schema::table('admission_cases', function (Blueprint $table) {
            $table->dropColumn('sla_alerted_at');
        });
        Schema::dropIfExists('assignment_rules');
    }
};
