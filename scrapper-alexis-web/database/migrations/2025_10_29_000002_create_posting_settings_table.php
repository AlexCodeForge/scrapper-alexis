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
        Schema::create('posting_settings', function (Blueprint $table) {
            $table->id();
            $table->string('page_name')->nullable();
            $table->integer('interval_min')->default(60); // minutes
            $table->integer('interval_max')->default(120); // minutes
            $table->boolean('enabled')->default(false);
            $table->timestamps();
        });

        // Insert default settings
        DB::table('posting_settings')->insert([
            'page_name' => null,
            'interval_min' => 60,
            'interval_max' => 120,
            'enabled' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posting_settings');
    }
};

