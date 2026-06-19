<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use BelongsToOrganization;
    use HasUlids;

    protected $fillable = ['name', 'description', 'status'];

    public function skus(): HasMany
    {
        return $this->hasMany(Sku::class);
    }

    public function payloadDecoderProfiles(): HasMany
    {
        return $this->hasMany(PayloadDecoderProfile::class);
    }

    public function reusablePayloadDecoderProfiles(): BelongsToMany
    {
        return $this->belongsToMany(PayloadDecoderProfile::class, 'payload_decoder_profile_product');
    }
}
