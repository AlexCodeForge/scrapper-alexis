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
        // Bugfix: Change default value of approved_for_posting from false to null
        // This ensures newly scraped messages show as "pending" instead of having false as default
        Schema::table('messages', function (Blueprint $table) {
            $table->boolean('approved_for_posting')->nullable()->default(null)->change();
        });

        // Bugfix: Update existing messages that were auto-set to false (due to old default)
        // to NULL so they appear in the "pending" filter
        // Only update messages where approved_at is NULL (meaning they were never explicitly rejected)
        \DB::table('messages')
            ->where('approved_for_posting', false)
            ->whereNull('approved_at')
            ->update(['approved_for_posting' => null]);

        \Log::info('Bugfix: Fixed approved_for_posting default value and updated existing pending messages');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original default of false
        Schema::table('messages', function (Blueprint $table) {
            $table->boolean('approved_for_posting')->default(false)->change();
        });

        // Note: We don't revert the data changes as that would be destructive
        \Log::info('Bugfix: Reverted approved_for_posting default value to false');
    }
};
