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
        Schema::table('scraper_settings', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('page_posting_debug_enabled');
            $table->string('username')->nullable()->after('display_name');
            $table->string('avatar_url')->nullable()->after('username');
            $table->boolean('verified')->default(false)->after('avatar_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scraper_settings', function (Blueprint $table) {
            $table->dropColumn(['display_name', 'username', 'avatar_url', 'verified']);
        });
    }
};
