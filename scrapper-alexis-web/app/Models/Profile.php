<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}

