<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained('organizations');
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('avatar_path', 500)->nullable()->after('password');
            $table->boolean('is_active')->default(true)->after('avatar_path');
            $table->string('data_scope', 20)->default('own')->after('is_active');
            $table->timestamp('last_login_at')->nullable()->after('data_scope');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn(['phone', 'avatar_path', 'is_active', 'data_scope', 'last_login_at']);
            $table->dropSoftDeletes();
        });
    }
};
