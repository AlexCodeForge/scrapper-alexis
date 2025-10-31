<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostingSetting extends Model
{
    protected $fillable = [
        'page_name',
        'page_url',
        'interval_min',
        'interval_max',
        'enabled',
        'auto_cleanup_enabled',
        'cleanup_days',
        'last_cleanup_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'auto_cleanup_enabled' => 'boolean',
        'interval_min' => 'integer',
        'interval_max' => 'integer',
        'cleanup_days' => 'integer',
        'last_cleanup_at' => 'datetime',
    ];

    /**
     * Get the singleton settings instance.
     */
    public static function getSettings(): self
    {
        return self::firstOrCreate([], [
            'page_name' => null,
            'interval_min' => 60,
            'interval_max' => 120,
            'enabled' => false,
            'auto_cleanup_enabled' => false,
            'cleanup_days' => 7,
        ]);
    }

    /**
     * Update the settings.
     */
    public static function updateSettings(array $data): bool
    {
        $settings = self::getSettings();
        return $settings->update($data);
    }
}

