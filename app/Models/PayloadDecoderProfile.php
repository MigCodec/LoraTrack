<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PayloadDecoderProfile extends Model
{
    use BelongsToOrganization;
    use HasUlids;

    protected $fillable = [
        'connector_id', 'product_id', 'name', 'enabled', 'priority', 'match_f_port',
        'match_path', 'match_value', 'observations_path', 'mac_path', 'rssi_path',
        'receiver_path', 'sample_payload',
    ];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'sample_payload' => 'array'];
    }

    public function connector(): BelongsTo
    {
        return $this->belongsTo(Connector::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'payload_decoder_profile_product');
    }
}
