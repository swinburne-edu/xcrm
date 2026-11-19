<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** One-time codes sent by SMS to log applicants into the portal (task 3.4). Only a hash of the code is stored. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_otps', function (Blueprint $table) {
            $table->id();
            $table->organizationId();
            $table->string('phone_normalized', 20);
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('expires_at');
            $table->dateTime('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['phone_normalized', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_otps');
    }
};
