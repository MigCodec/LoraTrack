<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZoneAlertRule extends Model
{
    use BelongsToOrganization;
    use HasUlids;

    protected $fillable = ['zone_id', 'event_type', 'dwell_minutes', 'recipients', 'enabled'];

    protected function casts(): array
    {
        return ['recipients' => 'array', 'enabled' => 'boolean', 'dwell_minutes' => 'integer'];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }
}
