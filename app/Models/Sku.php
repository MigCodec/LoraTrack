<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sku extends Model
{
    use BelongsToOrganization;
    use HasUlids;

    protected $fillable = [
        'product_id', 'code', 'normalized_code', 'name', 'base_unit', 'status', 'attributes',
    ];

    protected function casts(): array
    {
        return ['attributes' => 'array'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function externalReferences(): HasMany
    {
        return $this->hasMany(ExternalProductReference::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}
