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
            $table->boolean('approved_for_posting')->default(false)->after('image_path');
            $table->timestamp('approved_at')->nullable()->after('approved_for_posting');
            $table->boolean('posted_to_page')->default(false)->after('approved_at');
            $table->timestamp('posted_to_page_at')->nullable()->after('posted_to_page');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['approved_for_posting', 'approved_at', 'posted_to_page', 'posted_to_page_at']);
        });
    }
};

