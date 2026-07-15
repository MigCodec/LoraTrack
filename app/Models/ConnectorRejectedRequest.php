<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConnectorRejectedRequest extends Model
{
    use BelongsToOrganization;
    use HasUlids;

    protected $fillable = [
        'connector_id', 'request_id', 'http_status', 'reason', 'method', 'content_type',
        'declared_version', 'declared_type', 'source_ip_hash', 'context', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'occurred_at' => 'datetime',
            'http_status' => 'integer',
        ];
    }

    public function connector(): BelongsTo
    {
        return $this->belongsTo(Connector::class);
    }
}
