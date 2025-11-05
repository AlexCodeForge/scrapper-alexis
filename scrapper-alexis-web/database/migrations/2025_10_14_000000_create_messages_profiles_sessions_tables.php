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
        // Create profiles table
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->string('username')->nullable();
            $table->string('url')->unique();
            $table->string('credentials_reference')->nullable();
            $table->timestamp('last_scraped_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
        });

        // Create messages table
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->onDelete('cascade');
            $table->text('message_text');
            $table->string('message_hash')->unique();
            $table->boolean('posted_to_twitter')->default(false);
            $table->timestamp('posted_at')->nullable();
            $table->text('post_url')->nullable();
            $table->text('avatar_url')->nullable();
            $table->boolean('image_generated')->default(false);
            $table->string('image_path')->nullable();
            $table->timestamp('scraped_at')->nullable();
        });

        // Create scraping_sessions table
        Schema::create('scraping_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->onDelete('cascade');
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->integer('messages_found')->default(0);
            $table->integer('messages_new')->default(0);
            $table->string('stopped_reason')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scraping_sessions');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('profiles');
    }
};

