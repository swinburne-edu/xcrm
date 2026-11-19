<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 2.5: definitions of custom fields. The values live in the `custom_data` JSON column of each object.
 * No soft delete: the (organization, object, key) unique index must free the key once a field is removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_field_definitions', function (Blueprint $table) {
            $table->id();
            $table->organizationId();
            $table->string('object', 50);
            $table->string('key', 64);
            $table->string('label');
            $table->string('type', 20);
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_filterable')->default(true);
            $table->integer('sort_order')->default(0);
            $table->auditColumns();
            $table->timestamps();
            $table->unique(['organization_id', 'object', 'key']);
            $table->index(['organization_id', 'object', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_definitions');
    }
};
