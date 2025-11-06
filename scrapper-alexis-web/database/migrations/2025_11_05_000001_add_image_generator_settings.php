<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('scraper_settings', function (Blueprint $table) {
            $table->boolean('image_generator_enabled')->default(false)->after('page_posting_debug_enabled');
            $table->integer('image_generator_interval_min')->default(30)->after('image_generator_enabled'); // minutes
            $table->integer('image_generator_interval_max')->default(60)->after('image_generator_interval_min'); // minutes
            $table->boolean('image_generator_debug_enabled')->default(false)->after('image_generator_interval_max');
        });

        // Update existing settings row with default values
        DB::table('scraper_settings')
            ->where('id', 1)
            ->update([
                'image_generator_enabled' => false,
                'image_generator_interval_min' => 30,
                'image_generator_interval_max' => 60,
                'image_generator_debug_enabled' => false,
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scraper_settings', function (Blueprint $table) {
            $table->dropColumn([
                'image_generator_enabled',
                'image_generator_interval_min',
                'image_generator_interval_max',
                'image_generator_debug_enabled',
            ]);
        });
    }
};


