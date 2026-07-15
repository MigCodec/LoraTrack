<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Connectors\ConnectorRejectedRequestRecorder;
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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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

    public function receive(
        Request $request,
        Connector $connector,
        MerakiPayloadNormalizer $normalizer,
        ConnectorRejectedRequestRecorder $rejections,
    ): JsonResponse
    {
        abort_unless($connector->provider === ConnectorProvider::MerakiLocation, 404);
        if (strlen($request->getContent()) > 5 * 1024 * 1024) {
            $this->reject($rejections, $connector, $request, 413, 'payload_too_large', 'El payload excede 5 MB.');
        }
        if (! $request->isJson()) {
            $this->reject($rejections, $connector, $request, 415, 'unsupported_content_type', 'Meraki debe enviar application/json.');
        }
        if ($connector->status !== ConnectorStatus::Active || ! $connector->organization?->active) {
            $this->reject($rejections, $connector, $request, 404, 'connector_unavailable');
        }

        $payload = $request->json()->all();
        $expectedSecret = (string) ($connector->credentials['shared_secret'] ?? '');
        $providedSecret = (string) ($payload['secret'] ?? '');
        if ($expectedSecret === '' || ! hash_equals($expectedSecret, $providedSecret)) {
            $this->reject($rejections, $connector, $request, 401, 'authentication_failed');
        }

        $version = (string) ($payload['version'] ?? '');
        if (preg_match('/^(2|3)\.\d+$/', $version) !== 1) {
            $this->reject($rejections, $connector, $request, 422, 'invalid_version', 'Versión de Meraki inválida.');
        }
        $majorVersion = (int) explode('.', $version)[0];
        $expectedMajor = (int) ($connector->configuration['api_version'] ?? 3);
        if (! in_array($majorVersion, [2, 3], true) || $majorVersion !== $expectedMajor) {
            $this->reject($rejections, $connector, $request, 422, 'unsupported_version', 'Versión de Meraki no permitida por este conector.', [
                'expected_major' => $expectedMajor,
            ]);
        }
        $allowedTypes = $majorVersion === 3
            ? ['WiFi', 'BLE', 'Bluetooth']
            : ['DevicesSeen', 'BluetoothDevicesSeen'];
        if (! in_array($payload['type'] ?? null, $allowedTypes, true)) {
            $this->reject($rejections, $connector, $request, 422, 'invalid_observation_type', 'Tipo de observación Meraki inválido.');
        }

        try {
            $records = $normalizer->records($payload, $majorVersion);
        } catch (ValidationException $exception) {
            $this->reject($rejections, $connector, $request, 422, 'invalid_payload', 'Payload Meraki inválido.', [
                'invalid_fields' => array_keys($exception->errors()),
            ]);
        }
        if ($records === []) {
            $this->reject($rejections, $connector, $request, 422, 'empty_observations', 'El payload no contiene observaciones Meraki válidas.');
        }
        $configuredNetwork = trim((string) ($connector->configuration['network_id'] ?? ''));
        if ($configuredNetwork !== '') {
            if (! collect($records)->every(
                fn (array $record): bool => ($record['network_id'] ?? '') === $configuredNetwork,
            )) {
                $this->reject($rejections, $connector, $request, 422, 'network_mismatch', 'El networkId no corresponde al configurado.');
            }
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

    /** @param array<string, mixed> $context */
    private function reject(
        ConnectorRejectedRequestRecorder $rejections,
        Connector $connector,
        Request $request,
        int $status,
        string $reason,
        string $message = '',
        array $context = [],
    ): never {
        try {
            $rejections->record($connector, $request, $status, $reason, $context);
        } catch (\Throwable $exception) {
            Log::warning('No fue posible registrar un intento rechazado del conector.', [
                'connector_id' => $connector->id,
                'reason' => $reason,
                'exception' => $exception::class,
            ]);
        }

        abort($status, $message);
    }
}
