<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduledCommandStatus extends Model
{
    protected $primaryKey = 'task';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'last_started_at' => 'datetime',
            'last_finished_at' => 'datetime',
            'last_succeeded_at' => 'datetime',
            'last_failed_at' => 'datetime',
            'last_duration_ms' => 'integer',
            'last_exit_code' => 'integer',
            'run_count' => 'integer',
        ];
    }
}
