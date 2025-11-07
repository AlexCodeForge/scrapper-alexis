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
            // Add posting operating hours (12-hour format)
            $table->integer('posting_start_hour')->default(7)->after('timezone'); // 1-12
            $table->enum('posting_start_period', ['AM', 'PM'])->default('AM')->after('posting_start_hour');
            $table->integer('posting_stop_hour')->default(1)->after('posting_start_period'); // 1-12
            $table->enum('posting_stop_period', ['AM', 'PM'])->default('AM')->after('posting_stop_hour');
        });

        // Update existing settings row with default values (stop at 1AM, start at 7AM)
        DB::table('scraper_settings')
            ->where('id', 1)
            ->update([
                'posting_start_hour' => 7,
                'posting_start_period' => 'AM',
                'posting_stop_hour' => 1,
                'posting_stop_period' => 'AM',
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
                'posting_start_hour',
                'posting_start_period',
                'posting_stop_hour',
                'posting_stop_period',
            ]);
        });
    }
};
