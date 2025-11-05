<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
            // In Docker, message images are in shared volume at /app/data/message_images
            return '/app/data/message_images/' . basename($this->image_path);
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
}

