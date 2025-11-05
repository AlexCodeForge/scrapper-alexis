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
        Schema::table('messages', function (Blueprint $table) {
            // Add auto_post_enabled field - default true so approved images auto-post by default
            $table->boolean('auto_post_enabled')->default(true)->after('approved_at');
            
            // Add approval_type for tracking whether it was approved for auto or manual posting
            $table->string('approval_type', 20)->nullable()->after('auto_post_enabled');
            
            // Add index for performance when filtering auto-post enabled messages
            $table->index(['approved_for_posting', 'auto_post_enabled', 'posted_to_page'], 'idx_auto_post_queue');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('idx_auto_post_queue');
            $table->dropColumn(['auto_post_enabled', 'approval_type']);
        });
    }
};
