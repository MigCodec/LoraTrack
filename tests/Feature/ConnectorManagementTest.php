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

    public function test_meraki_form_offers_selectable_v2_and_v3_contracts(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get(route('connectors.create', 'meraki_location'))
            ->assertOk()
            ->assertSee('v3.x (recomendada)')
            ->assertSee('v2.1 (compatibilidad)')
            ->assertSee('Validator de Meraki')
            ->assertSee('Shared secret');
    }

    public function test_admin_can_replace_meraki_validator_or_secret_after_creation(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $connector = Connector::query()->create([
            'name' => 'Meraki planta',
            'provider' => ConnectorProvider::MerakiLocation,
            'kind' => 'telemetry',
            'status' => 'draft',
            'configuration' => ['api_version' => '3'],
            'credentials' => [
                'validator' => 'validator-original',
                'shared_secret' => 'shared-secret-original-value',
            ],
        ]);

        $this->actingAs($admin)->get(route('connectors.show', $connector))
            ->assertOk()
            ->assertSee('Actualizar validator o shared secret')
            ->assertSee('Validator proporcionado por Meraki')
            ->assertSee('Generar secret seguro')
            ->assertDontSee('validator-original')
            ->assertDontSee('shared-secret-original-value');

        $this->actingAs($admin)->put(route('connectors.meraki-credentials.update', $connector), [
            'shared_secret' => 'shared-secret-replaced-value',
        ])->assertRedirect();
        $connector->refresh();
        $this->assertSame('validator-original', $connector->credentials['validator']);
        $this->assertSame('shared-secret-replaced-value', $connector->credentials['shared_secret']);

        $this->actingAs($admin)->put(route('connectors.meraki-credentials.update', $connector), [
            'validator' => 'validator-from-meraki-dashboard',
        ])->assertRedirect();
        $connector->refresh();
        $this->assertSame('validator-from-meraki-dashboard', $connector->credentials['validator']);
        $this->assertSame('shared-secret-replaced-value', $connector->credentials['shared_secret']);
        $this->assertStringNotContainsString(
            'shared-secret-replaced-value',
            (string) DB::table('connectors')->where('id', $connector->id)->value('credentials'),
        );
        $this->assertDatabaseHas('connector_activity_logs', [
            'connector_id' => $connector->id,
            'event' => 'meraki_credentials_rotated',
        ]);
    }

    public function test_empty_meraki_credential_update_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $connector = Connector::query()->create([
            'name' => 'Meraki',
            'provider' => ConnectorProvider::MerakiLocation,
            'kind' => 'telemetry',
            'credentials' => [
                'validator' => 'validator-original',
                'shared_secret' => 'shared-secret-original-value',
            ],
        ]);

        $this->actingAs($admin)
            ->from(route('connectors.show', $connector))
            ->put(route('connectors.meraki-credentials.update', $connector), [])
            ->assertRedirect(route('connectors.show', $connector))
            ->assertSessionHasErrors('validator');
        $this->assertSame('validator-original', $connector->fresh()->credentials['validator']);
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
            ->assertOk()
            ->assertSee(route('connectors.events.show', [$connector, $event]))
            ->assertSee('Últimos intentos de recepción rechazados')
            ->assertSee(route('connectors.show', ['connector' => $connector, 'events' => 'processed']).'#telemetry', false)
            ->assertSee(route('connectors.show', ['connector' => $connector, 'events' => 'rejected']).'#rejected-requests', false);
        $this->actingAs($admin)->get(route('connectors.show', ['connector' => $connector, 'events' => 'failed']))
            ->assertOk()
            ->assertSee('Telemetría: fallida')
            ->assertSee('No hay telemetría para este filtro.');
        $this->actingAs($admin)->get(route('connectors.events.show', [$connector, $event]))
            ->assertOk()->assertSee('JSON original')->assertSee('tracker-visible')->assertSee('AABBCCDD');

        $otherConnector = Connector::query()->create([
            'name' => 'Otro', 'provider' => 'tti_webhook', 'kind' => 'telemetry', 'status' => 'active',
        ]);
        $this->actingAs($admin)->get(route('connectors.events.show', [$otherConnector, $event]))->assertNotFound();
    }

    public function test_connector_telemetry_counters_are_persisted_from_event_status_changes(): void
    {
        $connector = Connector::query()->create([
            'name' => 'TTI contadores',
            'provider' => 'tti_webhook',
            'kind' => 'telemetry',
            'status' => 'active',
        ]);

        $event = TelemetryEvent::query()->create([
            'connector_id' => $connector->id,
            'external_event_id' => hash('sha256', 'counter-event'),
            'event_type' => 'uplink',
            'received_at' => now(),
            'raw_payload' => [],
            'processing_status' => 'pending',
        ]);

        $this->assertSame(1, $connector->fresh()->telemetry_events_count);
        $this->assertSame(1, $connector->fresh()->pending_events_count);

        $event->update(['processing_status' => 'processed']);
        $connector->refresh();
        $this->assertSame(0, $connector->pending_events_count);
        $this->assertSame(1, $connector->processed_events_count);
        $this->assertSame(0, $connector->failed_events_count);

        $event->update(['processing_status' => 'failed']);
        $connector->refresh();
        $this->assertSame(1, $connector->telemetry_events_count);
        $this->assertSame(0, $connector->pending_events_count);
        $this->assertSame(0, $connector->processed_events_count);
        $this->assertSame(1, $connector->failed_events_count);
    }
}
