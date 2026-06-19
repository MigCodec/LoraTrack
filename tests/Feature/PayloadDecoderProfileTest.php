<?php

namespace Tests\Feature;

use App\Enums\ConnectorKind;
use App\Enums\ConnectorProvider;
use App\Models\Connector;
use App\Models\PayloadDecoderProfile;
use App\Models\Product;
use App\Positioning\PayloadProfileDecoder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayloadDecoderProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_tti_payload_is_normalized_using_saved_dot_paths(): void
    {
        $connector = Connector::query()->create([
            'name' => 'TTI planta', 'kind' => ConnectorKind::Telemetry,
            'provider' => ConnectorProvider::TtiWebhook,
        ]);
        $product = Product::query()->create(['name' => 'Bombas']);
        $profile = PayloadDecoderProfile::query()->create([
            'connector_id' => $connector->id, 'product_id' => $product->id,
            'name' => 'B1000 BLE scan', 'enabled' => true, 'priority' => 10,
            'match_f_port' => 10, 'match_path' => 'message.type', 'match_value' => 'ble_scan',
            'observations_path' => 'message.tags', 'mac_path' => 'address', 'rssi_path' => 'power',
            'receiver_path' => 'scanner.serial',
        ]);
        $raw = ['uplink_message' => ['f_port' => 10, 'decoded_payload' => [
            'message' => ['type' => 'ble_scan', 'tags' => [
                ['address' => 'AA:BB:CC:DD:EE:01', 'power' => -67],
            ]], 'scanner' => ['serial' => 'SCAN-01'],
        ]]];

        $result = app(PayloadProfileDecoder::class)->decode($raw, $connector);

        $this->assertTrue($result['profile']->is($profile));
        $this->assertSame('SCAN-01', $result['receiver_identifier']);
        $this->assertSame('AA:BB:CC:DD:EE:01', $result['decoded']['observations'][0]['mac']);
        $this->assertSame(-67, $result['decoded']['observations'][0]['rssi']);
    }

    public function test_non_matching_profile_leaves_original_payload_untouched(): void
    {
        $connector = Connector::query()->create([
            'name' => 'TTI', 'kind' => ConnectorKind::Telemetry,
            'provider' => ConnectorProvider::TtiWebhook,
        ]);
        PayloadDecoderProfile::query()->create([
            'connector_id' => $connector->id, 'name' => 'Puerto 10', 'enabled' => true,
            'priority' => 10, 'match_f_port' => 10, 'observations_path' => 'items',
            'mac_path' => 'mac', 'rssi_path' => 'rssi',
        ]);
        $decoded = ['observations' => [['mac' => '001122334455', 'rssi' => -70]]];

        $result = app(PayloadProfileDecoder::class)->decode([
            'uplink_message' => ['f_port' => 20, 'decoded_payload' => $decoded],
        ], $connector);

        $this->assertNull($result['profile']);
        $this->assertSame($decoded, $result['decoded']);
    }
}
