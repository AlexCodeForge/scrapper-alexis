<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Carbon;

class Profile extends Model
{
    protected $table = 'profiles';

    public $timestamps = false;

    protected $fillable = [
        'username',
        'url',
        'credentials_reference',
        'last_scraped_at',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_scraped_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function scrapingSessions()
    {
        return $this->hasMany(ScrapingSession::class);
    }

    /**
     * Convert last_scraped_at from UTC to application timezone
     * Dates are stored in UTC in database but should be displayed in app timezone
     */
    protected function lastScrapedAt(): Attribute
    {
        return Attribute::make(
            get: function (?string $value) {
                if (!$value) return null;
                return Carbon::parse($value, 'UTC')->timezone(config('app.timezone'));
            },
        );
    }
}

