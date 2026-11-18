<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-organization catalogs (docs/database.dbml §2): lead sources, lost reasons,
 * call results, tags and the polymorphic tag pivot. Columns follow the dbml:
 * no audit columns / soft deletes (rows are deactivated with is_active instead).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_sources', function (Blueprint $table) {
            $table->id();
            $table->organizationId();
            $table->string('code', 50);
            $table->string('name');
            $table->string('channel', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unique(['organization_id', 'code']);
        });

        Schema::create('lost_reasons', function (Blueprint $table) {
            $table->id();
            $table->organizationId();
            $table->string('name');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unique(['organization_id', 'name']);
        });

        Schema::create('call_results', function (Blueprint $table) {
            $table->id();
            $table->organizationId();
            $table->string('name');
            $table->boolean('is_contacted')->default(false);
            $table->integer('sort_order')->default(0);
            $table->unique(['organization_id', 'name']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->organizationId();
            $table->string('name', 100);
            $table->string('color', 20)->nullable();
            $table->unique(['organization_id', 'name']);
        });

        Schema::create('taggables', function (Blueprint $table) {
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->string('taggable_type', 100);
            $table->unsignedBigInteger('taggable_id');
            $table->primary(['tag_id', 'taggable_type', 'taggable_id']);
            $table->index(['taggable_type', 'taggable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('call_results');
        Schema::dropIfExists('lost_reasons');
        Schema::dropIfExists('lead_sources');
    }
};
