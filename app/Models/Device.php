<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    use BelongsToOrganization;
    use HasUlids;

    protected $fillable = ['identifier', 'name', 'type', 'model', 'status', 'metadata', 'last_seen_at'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'last_seen_at' => 'datetime'];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AssetDeviceAssignment::class);
    }

    public function installations(): HasMany
    {
        return $this->hasMany(DeviceInstallation::class);
    }
}
