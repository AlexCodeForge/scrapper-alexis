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
    ];

    protected $casts = [
        'posted_to_twitter' => 'boolean',
        'image_generated' => 'boolean',
        'downloaded' => 'boolean',
        'scraped_at' => 'datetime',
        'posted_at' => 'datetime',
        'downloaded_at' => 'datetime',
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
}

