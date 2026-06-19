<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConnectorKind;
use App\Enums\ConnectorProvider;
use App\Enums\ConnectorStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Connector extends Model
{
    use BelongsToOrganization;
    use HasUlids;

    protected $fillable = [
        'name', 'kind', 'provider', 'status', 'configuration', 'credentials',
        'contract_version', 'sync_cursor', 'last_activity_at', 'last_success_at',
        'last_tested_at', 'last_error',
    ];

    protected $hidden = ['credentials', 'sync_cursor'];

    protected function casts(): array
    {
        return [
            'kind' => ConnectorKind::class,
            'provider' => ConnectorProvider::class,
            'status' => ConnectorStatus::class,
            'configuration' => 'array',
            'credentials' => 'encrypted:array',
            'last_activity_at' => 'datetime',
            'last_success_at' => 'datetime',
            'last_tested_at' => 'datetime',
        ];
    }

    public function externalProductReferences(): HasMany
    {
        return $this->hasMany(ExternalProductReference::class);
    }

    public function telemetryEvents(): HasMany
    {
        return $this->hasMany(TelemetryEvent::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ConnectorActivityLog::class);
    }

    public function logActivity(string $event, string $message, string $level = 'info', array $context = []): ConnectorActivityLog
    {
        return $this->activityLogs()->create(compact('event', 'message', 'level', 'context'));
    }

    public function payloadDecoderProfiles(): HasMany
    {
        return $this->hasMany(PayloadDecoderProfile::class);
    }
}
