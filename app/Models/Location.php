<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use BelongsToOrganization;
    use HasUlids;

    protected $fillable = [
        'parent_id', 'type', 'name', 'coordinate_system', 'origin_latitude', 'origin_longitude', 'metadata',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'origin_latitude' => 'decimal:7', 'origin_longitude' => 'decimal:7'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function floorPlans(): HasMany
    {
        return $this->hasMany(FloorPlan::class);
    }
}
