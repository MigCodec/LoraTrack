<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class OrganizationScheduledTask extends Model
{
    use BelongsToOrganization;
    use HasUlids;

    protected $fillable = [
        'task',
        'enabled',
        'interval_minutes',
        'next_run_at',
        'last_started_at',
        'last_finished_at',
        'last_succeeded_at',
        'last_failed_at',
        'last_duration_ms',
        'last_exit_code',
        'last_error',
        'run_count',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'interval_minutes' => 'integer',
            'next_run_at' => 'datetime',
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
