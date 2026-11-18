<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Responsibility areas (docs/database.dbml §1): a territory belongs to a user and/or a team and
 * covers provinces and/or high schools (polymorphic, by morph alias).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('territories', function (Blueprint $table) {
            $table->id();
            $table->organizationId();
            $table->string('name');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->auditColumns();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('territory_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('territory_id')->constrained('territories')->cascadeOnDelete();
            $table->string('assignable_type', 100);
            $table->unsignedBigInteger('assignable_id');
            $table->unique(['territory_id', 'assignable_type', 'assignable_id'], 'territory_assignments_unique');
            $table->index(['assignable_type', 'assignable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('territory_assignments');
        Schema::dropIfExists('territories');
    }
};
