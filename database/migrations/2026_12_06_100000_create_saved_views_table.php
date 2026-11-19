<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 2.6: saved views of a list screen (filters, sort, page size, visible columns, table / kanban).
 * user_id = owner of a personal view; a shared view has user_id null (docs/database.dbml), its author stays in created_by.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_views', function (Blueprint $table) {
            $table->id();
            $table->organizationId();
            $table->string('object', 50);
            $table->string('name');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_shared')->default(false);
            $table->string('view_type', 10)->default('table');
            $table->json('config')->nullable();
            $table->integer('sort_order')->default(0);
            $table->auditColumns();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'object', 'is_shared']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_views');
    }
};
