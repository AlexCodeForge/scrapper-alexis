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
            // Add post_priority field - higher values = posted first (0 = normal priority)
            $table->integer('post_priority')->default(0)->after('approved_at');
            
            // Add index for performance when ordering by priority
            $table->index(['post_priority', 'approved_at'], 'idx_priority_queue');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('idx_priority_queue');
            $table->dropColumn('post_priority');
        });
    }
};
