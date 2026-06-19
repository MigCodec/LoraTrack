<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FloorPlan extends Model
{
    use BelongsToOrganization;
    use HasUlids;

    protected $fillable = [
        'location_id', 'name', 'disk', 'file_path', 'preview_path', 'original_name',
        'mime_type', 'width_meters', 'height_meters', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'width_meters' => 'decimal:3',
            'height_meters' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class);
    }

    public function drawablePath(): ?string
    {
        if ($this->preview_path) {
            return $this->preview_path;
        }

        return str_starts_with($this->mime_type, 'image/') ? $this->file_path : null;
    }
}
