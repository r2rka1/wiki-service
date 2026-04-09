<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FetchJob extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_DONE    = 'done';
    public const STATUS_FAILED  = 'failed';

    protected $fillable = [
        'id', 'user_id', 'status', 'started_at', 'finished_at', 'error',
    ];

    protected $casts = [
        'user_id'     => 'int',
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function getKeyType(): string
    {
        return 'string';
    }

    public function getIncrementing(): bool
    {
        return false;
    }
}
