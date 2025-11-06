<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Carbon;

class Message extends Model
{
    protected $table = 'messages';

    public $timestamps = false;

    protected $fillable = [
        'profile_id',
        'message_text',
        'message_hash',
        'posted_to_twitter',
        'posted_at',
        'post_url',
        'avatar_url',
        'image_generated',
        'image_path',
        'downloaded',
        'downloaded_at',
        'approved_for_posting',
        'approved_at',
        'auto_post_enabled',
        'approval_type',
        'posted_to_page',
        'posted_to_page_at',
    ];

    protected $casts = [
        'posted_to_twitter' => 'boolean',
        'image_generated' => 'boolean',
        'downloaded' => 'boolean',
        'approved_for_posting' => 'boolean',
        'auto_post_enabled' => 'boolean',
        'posted_to_page' => 'boolean',
        'scraped_at' => 'datetime',
        'posted_at' => 'datetime',
        'downloaded_at' => 'datetime',
        'approved_at' => 'datetime',
        'posted_to_page_at' => 'datetime',
    ];

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }

    public function getImageFullPathAttribute()
    {
        if ($this->image_path) {
            // Images are stored in public/message_images directory
            return public_path('message_images/' . basename($this->image_path));
        }
        return null;
    }

    public function getImageUrlAttribute()
    {
        if ($this->image_path) {
            return asset('message_images/' . basename($this->image_path));
        }
        return null;
    }

    public function scopeWithImages($query)
    {
        return $query->where('image_generated', true);
    }

    public function scopePostedToTwitter($query)
    {
        return $query->where('posted_to_twitter', true);
    }

    public function scopeUnposted($query)
    {
        return $query->where('posted_to_twitter', false);
    }

    public function scopeNotDownloaded($query)
    {
        return $query->where(function ($q) {
            $q->where('downloaded', false)->orWhereNull('downloaded');
        });
    }

    public function scopeDownloaded($query)
    {
        return $query->where('downloaded', true);
    }

    /**
     * Scope to filter messages with valid word count (more than 4 words)
     * Matches the scraper's message_deduplicator.py logic
     */
    public function scopeValidWordCount($query)
    {
        return $query->whereRaw("LENGTH(message_text) - LENGTH(REPLACE(message_text, ' ', '')) + 1 > 4");
    }

    /**
     * Scope to filter approved messages for posting
     */
    public function scopeApprovedForPosting($query)
    {
        return $query->where('approved_for_posting', true);
    }

    /**
     * Scope to filter messages not approved yet
     */
    public function scopePendingApproval($query)
    {
        return $query->where(function ($q) {
            $q->where('approved_for_posting', false)
              ->whereNull('approved_at');
        })->orWhere(function ($q) {
            $q->whereNull('approved_for_posting')
              ->whereNull('approved_at');
        });
    }

    /**
     * Scope to filter messages posted to page
     */
    public function scopePostedToPage($query)
    {
        return $query->where('posted_to_page', true);
    }

    /**
     * Scope to filter messages not posted to page yet
     */
    public function scopeNotPostedToPage($query)
    {
        return $query->where('posted_to_page', false)
            ->orWhereNull('posted_to_page');
    }

    /**
     * Scope to filter messages with auto-post enabled
     */
    public function scopeAutoPostEnabled($query)
    {
        return $query->where('auto_post_enabled', true);
    }

    /**
     * Scope to filter messages for manual posting only (auto-post disabled)
     */
    public function scopeManualPostOnly($query)
    {
        return $query->where('auto_post_enabled', false);
    }

    /**
     * BUGFIX: Timezone handling for datetime fields
     * 
     * Issue: SQLite doesn't have native timezone support, so Laravel stores datetime as text strings
     * in whatever timezone they're provided. This caused dates to be stored in app timezone
     * (America/Mexico_City) instead of UTC, leading to incorrect "future" timestamps.
     * 
     * Solution: Custom accessors/mutators to ensure:
     * 1. WRITE: Convert from app timezone to UTC before storing
     * 2. READ: Convert from UTC to app timezone for display
     * 
     * This ensures all dates are stored in UTC (single source of truth) and displayed in
     * the configured app timezone (America/Mexico_City).
     */

    /**
     * Accessor/Mutator for scraped_at - Store UTC, display app timezone
     */
    protected function scrapedAt(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Carbon::parse($value, 'UTC')->timezone(config('app.timezone')) : null,
            set: fn ($value) => $value ? Carbon::parse($value)->timezone('UTC')->toDateTimeString() : null,
        );
    }

    /**
     * Accessor/Mutator for posted_to_page_at - Store UTC, display app timezone
     */
    protected function postedToPageAt(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Carbon::parse($value, 'UTC')->timezone(config('app.timezone')) : null,
            set: fn ($value) => $value ? Carbon::parse($value)->timezone('UTC')->toDateTimeString() : null,
        );
    }

    /**
     * Accessor/Mutator for posted_at - Store UTC, display app timezone
     */
    protected function postedAt(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Carbon::parse($value, 'UTC')->timezone(config('app.timezone')) : null,
            set: fn ($value) => $value ? Carbon::parse($value)->timezone('UTC')->toDateTimeString() : null,
        );
    }

    /**
     * Accessor/Mutator for downloaded_at - Store UTC, display app timezone
     */
    protected function downloadedAt(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Carbon::parse($value, 'UTC')->timezone(config('app.timezone')) : null,
            set: fn ($value) => $value ? Carbon::parse($value)->timezone('UTC')->toDateTimeString() : null,
        );
    }

    /**
     * Accessor/Mutator for approved_at - Store UTC, display app timezone
     */
    protected function approvedAt(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Carbon::parse($value, 'UTC')->timezone(config('app.timezone')) : null,
            set: fn ($value) => $value ? Carbon::parse($value)->timezone('UTC')->toDateTimeString() : null,
        );
    }
}

