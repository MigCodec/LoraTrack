<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertRule extends Model
{
    use BelongsToOrganization;
    use HasUlids;

    protected $fillable = [
        'name', 'enabled', 'subject_type', 'subject_id', 'trigger_type', 'zone_id', 'threshold',
        'duration_minutes', 'cooldown_minutes', 'actions', 'recipient_roles', 'recipient_user_ids', 'last_triggered_at',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean', 'threshold' => 'float', 'duration_minutes' => 'integer',
            'cooldown_minutes' => 'integer', 'actions' => 'array', 'recipient_roles' => 'array',
            'recipient_user_ids' => 'array', 'last_triggered_at' => 'datetime',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'subject_id');
    }
}
