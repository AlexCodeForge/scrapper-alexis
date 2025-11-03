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
        Schema::create('scraper_settings', function (Blueprint $table) {
            $table->id();
            
            // Facebook Scraper Settings
            $table->boolean('facebook_enabled')->default(false);
            $table->integer('facebook_interval_min')->default(45);
            $table->integer('facebook_interval_max')->default(80);
            $table->text('facebook_email')->nullable();
            $table->text('facebook_password')->nullable(); // Will be encrypted
            $table->text('facebook_profiles')->nullable(); // Comma-separated URLs
            
            // Twitter Poster Settings
            $table->boolean('twitter_enabled')->default(false);
            $table->integer('twitter_interval_min')->default(8);
            $table->integer('twitter_interval_max')->default(60);
            $table->text('twitter_email')->nullable();
            $table->text('twitter_password')->nullable(); // Will be encrypted
            $table->string('twitter_display_name')->nullable();
            $table->string('twitter_username')->nullable();
            $table->text('twitter_avatar_url')->nullable();
            $table->boolean('twitter_verified')->default(false);
            
            // Proxy Settings
            $table->string('proxy_server')->nullable();
            $table->string('proxy_username')->nullable();
            $table->text('proxy_password')->nullable(); // Will be encrypted
            
            $table->timestamps();
        });

        // Insert default settings (singleton pattern - only one row)
        DB::table('scraper_settings')->insert([
            'facebook_enabled' => false,
            'facebook_interval_min' => 45,
            'facebook_interval_max' => 80,
            'twitter_enabled' => false,
            'twitter_interval_min' => 8,
            'twitter_interval_max' => 60,
            'twitter_verified' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scraper_settings');
    }
};

