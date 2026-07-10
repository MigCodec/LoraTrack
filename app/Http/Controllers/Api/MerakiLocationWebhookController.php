<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Connectors\Meraki\MerakiPayloadNormalizer;
use App\Enums\ConnectorProvider;
use App\Enums\ConnectorStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessMerakiLocationObservation;
use App\Models\Connector;
use App\Models\TelemetryEvent;
use App\Support\TelemetryTimestamp;
use App\Tenancy\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MerakiLocationWebhookController extends Controller
{
    public function validateReceiver(Connector $connector): Response
    {
        abort_unless(
            $connector->provider === ConnectorProvider::MerakiLocation
            && $connector->organization?->active,
            404,
        );
        $validator = (string) ($connector->credentials['validator'] ?? '');
        abort_if($validator === '', 404);

        return response($validator, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function receive(Request $request, Connector $connector, MerakiPayloadNormalizer $normalizer): JsonResponse
    {
        abort_if(strlen($request->getContent()) > 5 * 1024 * 1024, 413, 'El payload excede 5 MB.');
        abort_unless($request->isJson(), 415, 'Meraki debe enviar application/json.');
        abort_unless(
            $connector->provider === ConnectorProvider::MerakiLocation
            && $connector->status === ConnectorStatus::Active
            && $connector->organization?->active,
            404,
        );

        $payload = $request->json()->all();
        $expectedSecret = (string) ($connector->credentials['shared_secret'] ?? '');
        $providedSecret = (string) ($payload['secret'] ?? '');
        abort_unless($expectedSecret !== '' && hash_equals($expectedSecret, $providedSecret), 401);

        $version = (string) ($payload['version'] ?? '');
        abort_unless(preg_match('/^(2|3)\.\d+$/', $version) === 1, 422, 'Versión de Meraki inválida.');
        $majorVersion = (int) explode('.', $version)[0];
        $expectedMajor = (int) ($connector->configuration['api_version'] ?? 3);
        abort_unless(in_array($majorVersion, [2, 3], true) && $majorVersion === $expectedMajor, 422, 'Versión de Meraki no permitida por este conector.');
        $allowedTypes = $majorVersion === 3
            ? ['WiFi', 'BLE', 'Bluetooth']
            : ['DevicesSeen', 'BluetoothDevicesSeen'];
        abort_unless(in_array($payload['type'] ?? null, $allowedTypes, true), 422, 'Tipo de observación Meraki inválido.');

        $records = $normalizer->records($payload, $majorVersion);
        abort_if($records === [], 422, 'El payload no contiene observaciones Meraki válidas.');
        $configuredNetwork = trim((string) ($connector->configuration['network_id'] ?? ''));
        if ($configuredNetwork !== '') {
            abort_unless(collect($records)->every(
                fn (array $record): bool => ($record['network_id'] ?? '') === $configuredNetwork,
            ), 422, 'El networkId no corresponde al configurado.');
        }

        $context = app(OrganizationContext::class);
        $context->set($connector->organization);

        try {
            [$accepted, $duplicates] = DB::transaction(function () use ($connector, $records): array {
                $accepted = 0;
                $duplicates = 0;

                foreach ($records as $record) {
                    $externalEventId = hash('sha256', implode('|', [
                        $record['version'],
                        $record['type'],
                        $record['network_id'],
                        $record['client_mac'],
                        $record['observed_at'],
                        $record['external_floor_plan_id'],
                        $record['x'],
                        $record['y'],
                    ]));
                    $event = TelemetryEvent::query()->firstOrCreate(
                        ['connector_id' => $connector->id, 'external_event_id' => $externalEventId],
                        [
                            'event_type' => 'meraki_location',
                            'observed_at' => filled($record['observed_at'])
                                ? TelemetryTimestamp::parseProviderTime($record['observed_at'])
                                : null,
                            'received_at' => now(),
                            'raw_payload' => $record,
                            'processing_status' => 'pending',
                        ],
                    );

                    if ($event->wasRecentlyCreated) {
                        ProcessMerakiLocationObservation::dispatch($event->id)->afterCommit();
                        $accepted++;
                    } else {
                        $duplicates++;
                    }
                }

                return [$accepted, $duplicates];
            });

            $connector->forceFill(['last_activity_at' => now(), 'last_error' => null])->save();
            $connector->logActivity('meraki_payload_received', 'Observaciones recibidas desde Cisco Meraki.', 'info', [
                'accepted' => $accepted,
                'duplicates' => $duplicates,
                'version' => $version,
                'request_id' => (string) Str::uuid(),
            ]);

            return response()->json([
                'accepted' => true,
                'observations_queued' => $accepted,
                'duplicates' => $duplicates,
            ], 202);
        } finally {
            $context->set(null);
        }
    }
}
