<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConnectorActivityLog extends Model
{
    use BelongsToOrganization;
    use HasUlids;

    public $timestamps = false;

    protected $fillable = ['connector_id', 'level', 'event', 'message', 'context', 'created_at'];

    protected function casts(): array
    {
        return ['context' => 'array', 'created_at' => 'datetime'];
    }

    public function connector(): BelongsTo
    {
        return $this->belongsTo(Connector::class);
    }
}
