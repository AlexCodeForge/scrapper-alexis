<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('posting_settings', function (Blueprint $table) {
            $table->boolean('auto_cleanup_enabled')->default(false)->after('enabled');
            $table->integer('cleanup_days')->default(7)->after('auto_cleanup_enabled');
            $table->timestamp('last_cleanup_at')->nullable()->after('cleanup_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posting_settings', function (Blueprint $table) {
            $table->dropColumn(['auto_cleanup_enabled', 'cleanup_days', 'last_cleanup_at']);
        });
    }
};

