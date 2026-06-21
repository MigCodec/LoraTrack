<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PositionEstimate extends Model
{
    use BelongsToOrganization;
    use HasUlids;

    protected $fillable = [
        'asset_id', 'location_id', 'floor_plan_id', 'zone_id', 'telemetry_event_id', 'algorithm',
        'algorithm_version', 'x', 'y', 'raw_x', 'raw_y', 'z',
        'latitude', 'longitude', 'confidence', 'accuracy_meters', 'calculated_at', 'evidence', 'filter_state',
    ];

    protected function casts(): array
    {
        return ['calculated_at' => 'datetime', 'evidence' => 'array', 'filter_state' => 'array'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function floorPlan(): BelongsTo
    {
        return $this->belongsTo(FloorPlan::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function telemetryEvent(): BelongsTo
    {
        return $this->belongsTo(TelemetryEvent::class);
    }
}
