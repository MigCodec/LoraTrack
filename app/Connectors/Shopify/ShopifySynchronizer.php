<?php

declare(strict_types=1);

namespace App\Connectors\Shopify;

use App\Connectors\CatalogProductImporter;
use App\Models\Connector;
use Illuminate\Support\Facades\Http;

class ShopifySynchronizer
{
    public function __construct(private readonly CatalogProductImporter $importer) {}

    public function sync(Connector $connector): int
    {
        $cursor = null;
        $count = 0;
        $config = $connector->configuration;
        $url = 'https://'.preg_replace('#^https?://#', '', rtrim($config['shop_domain'], '/')).'/admin/api/'.$config['api_version'].'/graphql.json';
        $query = <<<'GQL'
query Products($cursor: String) { products(first: 50, after: $cursor) { pageInfo { hasNextPage endCursor } nodes { id title descriptionHtml status updatedAt variants(first: 100) { nodes { id sku title inventoryQuantity } } } } }
GQL;
        do {
            $payload = Http::withHeaders(['X-Shopify-Access-Token' => $connector->credentials['access_token']])->acceptJson()->post($url, ['query' => $query, 'variables' => ['cursor' => $cursor]])->throw()->json();
            if (! empty($payload['errors'])) {
                throw new \RuntimeException('Shopify GraphQL: '.json_encode($payload['errors']));
            }
            $products = $payload['data']['products'];
            foreach ($products['nodes'] as $product) {
                foreach ($product['variants']['nodes'] as $variant) {
                    if (blank($variant['sku'])) {
                        continue;
                    }
                    $count += (int) $this->importer->import($connector, ['external_id' => $variant['id'], 'sku' => $variant['sku'], 'name' => $product['title'].($variant['title'] !== 'Default Title' ? ' · '.$variant['title'] : ''), 'description' => strip_tags($product['descriptionHtml'] ?? ''), 'status' => strtolower($product['status']) === 'active' ? 'active' : 'inactive', 'external_updated_at' => $product['updatedAt'], 'attributes' => ['shopify_product_id' => $product['id'], 'inventory_quantity' => $variant['inventoryQuantity']]]);
                }
            }
            $cursor = $products['pageInfo']['hasNextPage'] ? $products['pageInfo']['endCursor'] : null;
        } while ($cursor);
        $connector->update(['last_success_at' => now(), 'last_error' => null]);

        return $count;
    }
}
