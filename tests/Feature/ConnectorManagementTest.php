<?php

namespace Tests\Feature;

use App\Enums\ConnectorProvider;
use App\Enums\UserRole;
use App\Models\Connector;
use App\Models\TelemetryEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConnectorManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_connector_catalog_displays_an_icon_for_every_provider(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->get(route('connectors.index'))->assertOk();

        foreach (ConnectorProvider::cases() as $provider) {
            $response->assertSee('data-provider-icon="'.$provider->value.'"', false);
        }
    }

    public function test_tti_form_offers_secure_token_generator(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get(route('connectors.create', 'tti_webhook'))
            ->assertOk()
            ->assertSee('id="generate-webhook-token"', false)
            ->assertSee('id="copy-webhook-token"', false)
            ->assertSee('crypto.getRandomValues', false)
            ->assertDontSee('Perfil de decoder');
    }

    public function test_admin_can_create_sap_connector_and_credentials_are_encrypted(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->post(route('connectors.store'), [
            'name' => 'SAP Producción',
            'provider' => ConnectorProvider::SapS4Hana->value,
            'configuration' => [
                'base_url' => 'https://sap.example.test',
                'api_path' => '/sap/opu/odata/sap/API_PRODUCT_SRV',
                'auth_type' => 'basic',
            ],
            'credentials' => ['username' => 'api-user', 'password' => 'top-secret'],
        ]);

        $response->assertRedirect(route('connectors.index'));
        $connector = Connector::query()->firstOrFail();
        $this->assertSame('api-user', $connector->credentials['username']);
        $this->assertStringNotContainsString('top-secret', (string) DB::table('connectors')->value('credentials'));
        $this->assertDatabaseHas('connector_activity_logs', ['connector_id' => $connector->id, 'event' => 'created']);
        $this->actingAs($admin)->get(route('connectors.show', $connector))
            ->assertOk()->assertSee('Log operacional')->assertSee('Conector creado como borrador');
    }

    public function test_inactive_connector_can_be_deleted_but_active_connector_must_be_disabled_first(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $draft = Connector::query()->create(['name' => 'MQTT temporal', 'provider' => 'mqtt', 'kind' => 'telemetry', 'status' => 'draft']);

        $this->actingAs($admin)->delete(route('connectors.destroy', $draft))->assertRedirect(route('connectors.index'));
        $this->assertDatabaseMissing('connectors', ['id' => $draft->id]);

        $active = Connector::query()->create(['name' => 'MQTT activo', 'provider' => 'mqtt', 'kind' => 'telemetry', 'status' => 'active']);
        $this->actingAs($admin)->delete(route('connectors.destroy', $active))->assertStatus(422);
        $this->assertDatabaseHas('connectors', ['id' => $active->id]);
    }

    public function test_tti_connector_shows_configuration_guide_and_can_rotate_token(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $connector = Connector::query()->create([
            'name' => 'TTI Webhook', 'provider' => 'tti_webhook', 'kind' => 'telemetry',
            'status' => 'draft', 'credentials' => ['webhook_token' => str_repeat('a', 32)],
        ]);

        $this->actingAs($admin)->get(route('connectors.show', $connector))
            ->assertOk()->assertSee('Tutorial de configuración')->assertSee(route('api.tti.ingest', $connector))->assertSee('An uplink message is received');
        $this->actingAs($admin)->post(route('connectors.rotate-webhook-token', $connector))
            ->assertRedirect()->assertSessionHas('new_webhook_token');
        $this->assertNotSame(str_repeat('a', 32), $connector->fresh()->credentials['webhook_token']);
    }

    public function test_admin_can_open_the_raw_payload_of_a_connector_event(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $connector = Connector::query()->create([
            'name' => 'TTI Bodega', 'provider' => 'tti_webhook', 'kind' => 'telemetry', 'status' => 'active',
        ]);
        $event = TelemetryEvent::query()->create([
            'connector_id' => $connector->id,
            'external_event_id' => hash('sha256', 'payload-visible'),
            'event_type' => 'uplink',
            'received_at' => now(),
            'raw_payload' => ['end_device_ids' => ['device_id' => 'tracker-visible'], 'uplink_message' => ['f_cnt' => 17]],
            'normalized_payload' => ['device_identifier' => 'AABBCCDD'],
            'processing_status' => 'processed',
        ]);

        $this->actingAs($admin)->get(route('connectors.show', $connector))
            ->assertOk()->assertSee(route('connectors.events.show', [$connector, $event]));
        $this->actingAs($admin)->get(route('connectors.events.show', [$connector, $event]))
            ->assertOk()->assertSee('JSON original')->assertSee('tracker-visible')->assertSee('AABBCCDD');

        $otherConnector = Connector::query()->create([
            'name' => 'Otro', 'provider' => 'tti_webhook', 'kind' => 'telemetry', 'status' => 'active',
        ]);
        $this->actingAs($admin)->get(route('connectors.events.show', [$otherConnector, $event]))->assertNotFound();
    }
}
