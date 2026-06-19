<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignalObservation extends Model
{
    use BelongsToOrganization;
    use HasUlids;

    protected $fillable = [
        'telemetry_event_id', 'transmitter_mac', 'receiver_identifier', 'rssi', 'observed_at', 'metadata',
    ];

    protected function casts(): array
    {
        return ['observed_at' => 'datetime', 'metadata' => 'array'];
    }

    public function telemetryEvent(): BelongsTo
    {
        return $this->belongsTo(TelemetryEvent::class);
    }
}
