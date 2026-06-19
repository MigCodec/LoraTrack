<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Zone extends Model
{
    use BelongsToOrganization;
    use HasUlids;

    protected $fillable = [
        'floor_plan_id', 'name', 'code', 'color', 'shape', 'x_min', 'y_min', 'x_max', 'y_max', 'geometry',
    ];

    protected function casts(): array
    {
        return ['geometry' => 'array'];
    }

    public function floorPlan(): BelongsTo
    {
        return $this->belongsTo(FloorPlan::class);
    }

    public function alertRules(): HasMany
    {
        return $this->hasMany(ZoneAlertRule::class);
    }

    public function contains(float $x, float $y): bool
    {
        return $x >= (float) $this->x_min && $x <= (float) $this->x_max
            && $y >= (float) $this->y_min && $y <= (float) $this->y_max;
    }
}
