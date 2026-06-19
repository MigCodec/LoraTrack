<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelemetryEvent extends Model
{
    use BelongsToOrganization;
    use HasUlids;

    protected $fillable = [
        'connector_id', 'device_id', 'external_event_id', 'event_type', 'observed_at',
        'received_at', 'processed_at', 'normalized_payload', 'raw_payload',
        'processing_status', 'processing_error',
    ];

    protected function casts(): array
    {
        return [
            'observed_at' => 'datetime', 'received_at' => 'datetime', 'processed_at' => 'datetime',
            'normalized_payload' => 'array', 'raw_payload' => 'array',
        ];
    }

    public function connector(): BelongsTo
    {
        return $this->belongsTo(Connector::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function signalObservations(): HasMany
    {
        return $this->hasMany(SignalObservation::class);
    }
}
