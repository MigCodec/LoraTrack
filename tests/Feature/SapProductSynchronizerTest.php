<?php

namespace Tests\Feature;

use App\Connectors\Sap\SapProductSynchronizer;
use App\Enums\ConnectorKind;
use App\Enums\ConnectorProvider;
use App\Models\Connector;
use App\Models\ExternalProductReference;
use App\Models\Sku;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SapProductSynchronizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_sap_product_is_normalized_without_losing_leading_zeroes(): void
    {
        Http::fake([
            '*' => Http::response([
                'd' => ['results' => [[
                    'Product' => '000000000000104532',
                    'ProductDescription' => 'Bomba industrial 25 HP',
                    'BaseUnit' => 'EA',
                    'ProductType' => 'HAWA',
                    'ProductGroup' => 'PUMPS',
                ]]],
            ]),
        ]);
        $connector = Connector::query()->create([
            'name' => 'SAP',
            'kind' => ConnectorKind::Catalog,
            'provider' => ConnectorProvider::SapS4Hana,
            'configuration' => [
                'base_url' => 'https://sap.example.test',
                'api_path' => '/sap/opu/odata/sap/API_PRODUCT_SRV',
                'auth_type' => 'basic',
            ],
            'credentials' => ['username' => 'user', 'password' => 'secret'],
        ]);

        $count = app(SapProductSynchronizer::class)->sync($connector);

        $this->assertSame(1, $count);
        $this->assertSame('000000000000104532', Sku::query()->firstOrFail()->code);
        $this->assertDatabaseHas('external_product_references', [
            'connector_id' => $connector->id,
            'external_id' => '000000000000104532',
        ]);
        $this->assertSame(1, ExternalProductReference::query()->count());
    }
}
