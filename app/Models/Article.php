<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    protected $fillable = [
        'user_id', 'external_id', 'title', 'summary',
        'content', 'source_url', 'fetched_at',
    ];

    protected $casts = [
        'user_id'    => 'int',
        'fetched_at' => 'datetime',
    ];
}
