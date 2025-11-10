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
            // Drop the foreign key constraint
            $table->dropForeign(['profile_id']);
            
            // Make profile_id nullable
            $table->foreignId('profile_id')->nullable()->change();
            
            // Re-add the foreign key constraint with nullable support
            $table->foreign('profile_id')
                  ->references('id')
                  ->on('profiles')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // Drop the foreign key constraint
            $table->dropForeign(['profile_id']);
            
            // Make profile_id not nullable again
            $table->foreignId('profile_id')->nullable(false)->change();
            
            // Re-add the foreign key constraint
            $table->foreign('profile_id')
                  ->references('id')
                  ->on('profiles')
                  ->onDelete('cascade');
        });
    }
};
