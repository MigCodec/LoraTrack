<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalibrationRun extends Model
{
    use BelongsToOrganization;
    use HasUlids;

    protected $fillable = [
        'floor_plan_id', 'user_id', 'name', 'anchor_type', 'status', 'expected_x', 'expected_y',
        'calculated_x', 'calculated_y', 'position_error_meters', 'signal_rmse_meters',
        'confidence', 'parameters', 'applied_at',
    ];

    protected function casts(): array
    {
        return ['parameters' => 'array', 'applied_at' => 'datetime'];
    }

    public function floorPlan(): BelongsTo
    {
        return $this->belongsTo(FloorPlan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
