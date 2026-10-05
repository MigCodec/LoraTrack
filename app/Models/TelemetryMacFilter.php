<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelemetryMacFilter extends Model
{
    use BelongsToOrganization;
    use HasUlids;

    protected $fillable = ['device_id', 'normalized_mac', 'label', 'enabled'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
