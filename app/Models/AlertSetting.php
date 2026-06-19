<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class AlertSetting extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['enabled', 'recipients', 'offline_minutes', 'minimum_confidence', 'enabled_types'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'recipients' => 'array', 'enabled_types' => 'array', 'minimum_confidence' => 'float'];
    }

    public static function current(): self
    {
        return self::query()->firstOrCreate([], ['recipients' => [], 'enabled_types' => ['device_offline', 'connector_error', 'low_confidence']]);
    }
}
