<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalProductReference extends Model
{
    use BelongsToOrganization;
    use HasUlids;

    protected $fillable = [
        'connector_id', 'sku_id', 'external_id', 'external_code', 'payload_checksum',
        'external_updated_at', 'last_synced_at',
    ];

    protected function casts(): array
    {
        return ['external_updated_at' => 'datetime', 'last_synced_at' => 'datetime'];
    }

    public function connector(): BelongsTo
    {
        return $this->belongsTo(Connector::class);
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }
}
