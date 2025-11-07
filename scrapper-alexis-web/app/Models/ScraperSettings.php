<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class ScraperSettings extends Model
{
    protected $fillable = [
        'facebook_enabled',
        'facebook_interval_min',
        'facebook_interval_max',
        'facebook_email',
        'facebook_password',
        'facebook_profiles',
        'facebook_debug_enabled',
        'twitter_enabled',
        'twitter_interval_min',
        'twitter_interval_max',
        'twitter_email',
        'twitter_password',
        'twitter_debug_enabled',
        'proxy_server',
        'proxy_username',
        'proxy_password',
        'page_posting_debug_enabled',
        'display_name',
        'username',
        'avatar_url',
        'verified',
        'tweet_template_padding_enabled',
        'image_generator_enabled',
        'image_generator_interval_min',
        'image_generator_interval_max',
        'image_generator_debug_enabled',
        'timezone',
        'posting_start_hour',
        'posting_start_period',
        'posting_stop_hour',
        'posting_stop_period',
    ];

    protected $casts = [
        'facebook_enabled' => 'boolean',
        'facebook_interval_min' => 'integer',
        'facebook_interval_max' => 'integer',
        'facebook_debug_enabled' => 'boolean',
        'twitter_enabled' => 'boolean',
        'twitter_interval_min' => 'integer',
        'twitter_interval_max' => 'integer',
        'twitter_debug_enabled' => 'boolean',
        'page_posting_debug_enabled' => 'boolean',
        'verified' => 'boolean',
        'tweet_template_padding_enabled' => 'boolean',
        'image_generator_enabled' => 'boolean',
        'image_generator_interval_min' => 'integer',
        'image_generator_interval_max' => 'integer',
        'image_generator_debug_enabled' => 'boolean',
        'posting_start_hour' => 'integer',
        'posting_stop_hour' => 'integer',
    ];

    /**
     * Encrypt sensitive password fields using Laravel's encryption.
     * Best practice from Laravel 12 docs: Use Attribute for encryption with DecryptException handling
     */
    protected function facebookPassword(): Attribute
    {
        return Attribute::make(
            get: function (?string $value) {
                if (!$value) return null;
                try {
                    return decrypt($value);
                } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                    // Value is not encrypted (legacy data), return as-is
                    return $value;
                }
            },
            set: fn (?string $value) => $value ? encrypt($value) : null,
        );
    }

    protected function twitterPassword(): Attribute
    {
        return Attribute::make(
            get: function (?string $value) {
                if (!$value) return null;
                try {
                    return decrypt($value);
                } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                    // Value is not encrypted (legacy data), return as-is
                    return $value;
                }
            },
            set: fn (?string $value) => $value ? encrypt($value) : null,
        );
    }

    protected function proxyPassword(): Attribute
    {
        return Attribute::make(
            get: function (?string $value) {
                if (!$value) return null;
                try {
                    return decrypt($value);
                } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                    // Value is not encrypted (legacy data), return as-is
                    return $value;
                }
            },
            set: fn (?string $value) => $value ? encrypt($value) : null,
        );
    }

    /**
     * Get the singleton settings instance.
     * Follows the same pattern as PostingSetting model.
     */
    public static function getSettings(): self
    {
        return self::firstOrCreate([], [
            'facebook_enabled' => false,
            'facebook_interval_min' => 45,
            'facebook_interval_max' => 80,
            'twitter_enabled' => false,
            'twitter_interval_min' => 8,
            'twitter_interval_max' => 60,
            'verified' => false,
            'image_generator_enabled' => false,
            'image_generator_interval_min' => 30,
            'image_generator_interval_max' => 60,
            'image_generator_debug_enabled' => false,
            'timezone' => 'America/Mexico_City',
            'posting_start_hour' => 7,
            'posting_start_period' => 'AM',
            'posting_stop_hour' => 1,
            'posting_stop_period' => 'AM',
        ]);
    }

    /**
     * Update the settings (singleton).
     */
    public static function updateSettings(array $data): bool
    {
        $settings = self::getSettings();
        return $settings->update($data);
    }

    /**
     * Export settings to array format for Python scripts.
     * Decrypts passwords for external consumption.
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        // Parse facebook_profiles from comma-separated to array
        if (isset($array['facebook_profiles']) && $array['facebook_profiles']) {
            $array['facebook_profiles_array'] = array_filter(
                explode(',', str_replace("\n", ',', $array['facebook_profiles']))
            );
        } else {
            $array['facebook_profiles_array'] = [];
        }

        return $array;
    }
}

