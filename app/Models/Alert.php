<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    use BelongsToOrganization;
    use HasUlids;

    protected $fillable = ['fingerprint', 'type', 'severity', 'title', 'message', 'context', 'detected_at', 'resolved_at', 'notified_at'];

    protected function casts(): array
    {
        return ['context' => 'array', 'detected_at' => 'datetime', 'resolved_at' => 'datetime', 'notified_at' => 'datetime'];
    }
}
