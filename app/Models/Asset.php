<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Asset extends Model
{
    use BelongsToOrganization;
    use HasUlids;

    protected $fillable = [
        'sku_id', 'location_id', 'asset_tag', 'serial_number', 'name', 'mobility', 'status', 'metadata', 'photo_path',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function deviceAssignments(): HasMany
    {
        return $this->hasMany(AssetDeviceAssignment::class);
    }

    public function latestPosition(): HasOne
    {
        return $this->hasOne(PositionEstimate::class)->latestOfMany('calculated_at');
    }
}
