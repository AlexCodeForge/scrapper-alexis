<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScrapingSession extends Model
{
    protected $table = 'scraping_sessions';

    public $timestamps = false;

    protected $fillable = [
        'profile_id',
        'started_at',
        'completed_at',
        'messages_found',
        'messages_new',
        'stopped_reason',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }
}







