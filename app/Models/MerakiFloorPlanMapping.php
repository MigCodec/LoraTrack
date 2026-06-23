<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerakiFloorPlanMapping extends Model
{
    use BelongsToOrganization;
    use HasUlids;

    protected $fillable = [
        'connector_id',
        'floor_plan_id',
        'external_floor_plan_id',
        'external_floor_plan_name',
        'invert_y',
    ];

    protected function casts(): array
    {
        return ['invert_y' => 'boolean'];
    }

    public function connector(): BelongsTo
    {
        return $this->belongsTo(Connector::class);
    }

    public function floorPlan(): BelongsTo
    {
        return $this->belongsTo(FloorPlan::class);
    }
}
