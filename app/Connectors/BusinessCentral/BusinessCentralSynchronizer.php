<?php

declare(strict_types=1);

namespace App\Connectors\BusinessCentral;

use App\Connectors\CatalogProductImporter;
use App\Models\Connector;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class BusinessCentralSynchronizer
{
    public function __construct(private readonly CatalogProductImporter $importer) {}

    public function sync(Connector $connector): int
    {
        $c = $connector->configuration;
        $s = $connector->credentials;
        $token = Http::asForm()->timeout(20)->post('https://login.microsoftonline.com/'.urlencode($c['tenant_id']).'/oauth2/v2.0/token', ['grant_type' => 'client_credentials', 'client_id' => $s['client_id'], 'client_secret' => $s['client_secret'], 'scope' => 'https://api.businesscentral.dynamics.com/.default'])->throw()->json('access_token');
        $url = rtrim($c['base_url'] ?? 'https://api.businesscentral.dynamics.com/v2.0', '/').'/'.rawurlencode($c['tenant_id']).'/'.rawurlencode($c['environment']).'/api/v2.0/companies('.rawurlencode($c['company_id']).')/items?$top=200';
        $count = 0;
        while ($url) {
            $payload = Http::withToken($token)->acceptJson()->timeout(30)->get($url)->throw()->json();
            foreach (Arr::get($payload, 'value', []) as $item) {
                $count += (int) $this->importer->import($connector, ['external_id' => (string) $item['id'], 'sku' => (string) $item['number'], 'name' => (string) ($item['displayName'] ?? $item['number']), 'base_unit' => $item['baseUnitOfMeasureCode'] ?? null, 'status' => ($item['blocked'] ?? false) ? 'inactive' : 'active', 'attributes' => Arr::only($item, ['type', 'inventory', 'unitPrice'])]);
            }
            $url = $payload['@odata.nextLink'] ?? null;
        }
        $connector->update(['last_success_at' => now(), 'last_error' => null]);

        return $count;
    }
}
