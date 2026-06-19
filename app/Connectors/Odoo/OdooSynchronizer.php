<?php

declare(strict_types=1);

namespace App\Connectors\Odoo;

use App\Connectors\CatalogProductImporter;
use App\Models\Connector;
use Illuminate\Support\Facades\Http;

class OdooSynchronizer
{
    public function __construct(private readonly CatalogProductImporter $importer) {}

    public function sync(Connector $connector): int
    {
        $offset = 0;
        $count = 0;
        $limit = 200;
        do {
            $response = Http::withToken($connector->credentials['api_key'])->withHeaders(['X-Odoo-Database' => $connector->configuration['database']])->acceptJson()->post(rtrim($connector->configuration['base_url'], '/').'/json/2/product.product/search_read', ['domain' => [], 'fields' => ['id', 'default_code', 'display_name', 'active', 'uom_name', 'write_date', 'barcode'], 'limit' => $limit, 'offset' => $offset])->throw()->json();
            $items = is_array($response) && array_is_list($response) ? $response : ($response['result'] ?? []);
            foreach ($items as $item) {
                if (blank($item['default_code'] ?? null)) {
                    continue;
                }
                $count += (int) $this->importer->import($connector, ['external_id' => (string) $item['id'], 'sku' => $item['default_code'], 'name' => $item['display_name'], 'base_unit' => $item['uom_name'] ?? null, 'status' => ($item['active'] ?? true) ? 'active' : 'inactive', 'external_updated_at' => $item['write_date'] ?? null, 'attributes' => ['barcode' => $item['barcode'] ?? null]]);
            }
            $offset += $limit;
        } while (count($items) === $limit);
        $connector->update(['last_success_at' => now(), 'last_error' => null]);

        return $count;
    }
}
