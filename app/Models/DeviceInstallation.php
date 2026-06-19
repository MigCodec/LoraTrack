<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceInstallation extends Model
{
    use BelongsToOrganization;
    use HasUlids;

    protected $fillable = [
        'device_id', 'location_id', 'x', 'y', 'z', 'latitude', 'longitude', 'reference_rssi',
        'path_loss_exponent', 'started_at', 'ended_at',
    ];

    protected function casts(): array
    {
        return ['x' => 'float', 'y' => 'float', 'z' => 'float', 'reference_rssi' => 'integer', 'path_loss_exponent' => 'float', 'started_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
