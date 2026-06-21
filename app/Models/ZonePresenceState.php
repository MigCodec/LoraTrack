<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class ZonePresenceState extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['zone_id', 'asset_id', 'is_inside', 'entered_at', 'exited_at', 'last_evaluated_at', 'dwell_notified_at', 'last_position_estimate_id'];

    protected function casts(): array
    {
        return ['is_inside' => 'boolean', 'entered_at' => 'datetime', 'exited_at' => 'datetime', 'last_evaluated_at' => 'datetime', 'dwell_notified_at' => 'datetime'];
    }
}
