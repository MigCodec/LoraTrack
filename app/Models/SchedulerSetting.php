<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchedulerSetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'use_system_recommended' => 'boolean',
        ];
    }
}
