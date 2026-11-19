<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily spending of a campaign (task 2.8). campaigns.actual_cost is kept equal to the sum of these rows
 * once a campaign has any.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->date('date');
            $table->decimal('amount', 15, 0);
            $table->string('note')->nullable();
            $table->auditColumns();
            $table->timestamps();
            $table->index(['campaign_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_costs');
    }
};
