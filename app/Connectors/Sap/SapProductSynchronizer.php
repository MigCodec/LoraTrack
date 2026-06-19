<?php

declare(strict_types=1);

namespace App\Connectors\Sap;

use App\Connectors\ConnectorConnectionTester;
use App\Models\Connector;
use App\Models\ExternalProductReference;
use App\Models\Product;
use App\Models\Sku;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SapProductSynchronizer
{
    public function __construct(private readonly ConnectorConnectionTester $tester) {}

    public function sync(Connector $connector): int
    {
        $baseUrl = rtrim((string) Arr::get($connector->configuration, 'base_url'), '/');
        $apiPath = trim((string) Arr::get($connector->configuration, 'api_path'), '/');
        $url = "$baseUrl/$apiPath/A_Product?\$format=json&\$top=100";
        $count = 0;

        while ($url !== '') {
            $response = $this->tester->authenticatedRequest($connector)->get($url);
            if (! $response->successful()) {
                throw new RuntimeException('SAP product sync failed with HTTP '.$response->status().'.');
            }

            $payload = $response->json();
            $items = Arr::get($payload, 'd.results', Arr::get($payload, 'value', []));
            foreach (is_array($items) ? $items : [] as $item) {
                if (is_array($item) && $this->upsertProduct($connector, $item)) {
                    $count++;
                }
            }

            $url = (string) (Arr::get($payload, 'd.__next') ?? Arr::get($payload, '@odata.nextLink') ?? '');
        }

        $connector->forceFill(['last_success_at' => now(), 'last_error' => null])->save();

        return $count;
    }

    /** @param array<string, mixed> $item */
    private function upsertProduct(Connector $connector, array $item): bool
    {
        $externalId = trim((string) ($item['Product'] ?? $item['Material'] ?? ''));
        if ($externalId === '') {
            return false;
        }

        $checksum = hash('sha256', json_encode($item, JSON_THROW_ON_ERROR));
        $existing = ExternalProductReference::query()
            ->where('connector_id', $connector->id)
            ->where('external_id', $externalId)
            ->first();
        if ($existing?->payload_checksum === $checksum) {
            $existing->update(['last_synced_at' => now()]);

            return true;
        }

        DB::transaction(function () use ($connector, $item, $externalId, $checksum, $existing): void {
            $normalizedCode = mb_strtoupper(trim($externalId));
            $sku = $existing?->sku ?? Sku::query()->where('normalized_code', $normalizedCode)->first();
            $name = trim((string) ($item['ProductDescription'] ?? $item['Description'] ?? $externalId));

            if (! $sku) {
                $product = Product::query()->create(['name' => $name, 'status' => 'active']);
                $sku = $product->skus()->create([
                    'code' => $externalId,
                    'normalized_code' => $normalizedCode,
                    'name' => $name,
                    'base_unit' => $item['BaseUnit'] ?? null,
                    'status' => 'active',
                    'attributes' => Arr::only($item, ['ProductType', 'ProductGroup', 'BaseUnit']),
                ]);
            } else {
                $sku->update([
                    'name' => $name,
                    'base_unit' => $item['BaseUnit'] ?? $sku->base_unit,
                    'attributes' => array_merge($sku->attributes ?? [], Arr::only($item, ['ProductType', 'ProductGroup', 'BaseUnit'])),
                ]);
                $sku->product()->update(['name' => $name]);
            }

            ExternalProductReference::query()->updateOrCreate(
                ['connector_id' => $connector->id, 'external_id' => $externalId],
                [
                    'sku_id' => $sku->id,
                    'external_code' => $externalId,
                    'payload_checksum' => $checksum,
                    'last_synced_at' => now(),
                ],
            );
        });

        return true;
    }
}
