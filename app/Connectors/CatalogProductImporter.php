<?php

declare(strict_types=1);

namespace App\Connectors;

use App\Models\Connector;
use App\Models\ExternalProductReference;
use App\Models\Product;
use App\Models\Sku;
use Illuminate\Support\Facades\DB;

class CatalogProductImporter
{
    /** @param array{external_id:string,sku:string,name:string,description?:?string,base_unit?:?string,status?:string,attributes?:array<string,mixed>,external_updated_at?:mixed} $data */
    public function import(Connector $connector, array $data): bool
    {
        $externalId = trim($data['external_id']);
        $code = trim($data['sku']);
        if ($externalId === '' || $code === '') {
            return false;
        }
        $checksum = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
        $reference = ExternalProductReference::query()->where('connector_id', $connector->id)->where('external_id', $externalId)->first();
        if ($reference?->payload_checksum === $checksum) {
            $reference->update(['last_synced_at' => now()]);

            return true;
        }

        DB::transaction(function () use ($connector, $data, $externalId, $code, $checksum, $reference): void {
            $normalized = mb_strtoupper($code);
            $sku = $reference?->sku ?? Sku::query()->where('normalized_code', $normalized)->first();
            if (! $sku) {
                $product = Product::query()->create(['name' => $data['name'], 'description' => $data['description'] ?? null, 'status' => $data['status'] ?? 'active']);
                $sku = $product->skus()->create(['code' => $code, 'normalized_code' => $normalized, 'name' => $data['name'], 'base_unit' => $data['base_unit'] ?? null, 'status' => $data['status'] ?? 'active', 'attributes' => $data['attributes'] ?? []]);
            } else {
                $sku->update(['name' => $data['name'], 'base_unit' => $data['base_unit'] ?? $sku->base_unit, 'status' => $data['status'] ?? 'active', 'attributes' => array_merge($sku->attributes ?? [], $data['attributes'] ?? [])]);
                $sku->product()->update(['name' => $data['name'], 'description' => $data['description'] ?? $sku->product->description]);
            }
            ExternalProductReference::query()->updateOrCreate(
                ['connector_id' => $connector->id, 'external_id' => $externalId],
                ['sku_id' => $sku->id, 'external_code' => $code, 'payload_checksum' => $checksum, 'external_updated_at' => $data['external_updated_at'] ?? null, 'last_synced_at' => now()],
            );
        });

        return true;
    }
}
