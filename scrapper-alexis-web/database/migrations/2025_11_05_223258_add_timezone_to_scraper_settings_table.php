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
            $table->string('timezone')->default('America/Mexico_City')->after('image_generator_debug_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scraper_settings', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
