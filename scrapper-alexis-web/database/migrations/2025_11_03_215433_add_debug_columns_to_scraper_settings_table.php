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
            // Add debug output enable/disable for each script type
            $table->boolean('facebook_debug_enabled')->default(false)->after('facebook_profiles');
            $table->boolean('twitter_debug_enabled')->default(false)->after('twitter_verified');
            $table->boolean('page_posting_debug_enabled')->default(false)->after('proxy_password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scraper_settings', function (Blueprint $table) {
            $table->dropColumn(['facebook_debug_enabled', 'twitter_debug_enabled', 'page_posting_debug_enabled']);
        });
    }
};
