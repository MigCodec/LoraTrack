<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Connectors\ConnectorRejectedRequestRecorder;
use App\Enums\ConnectorProvider;
use App\Enums\ConnectorStatus;
use App\Http\Controllers\Controller;
use App\Models\Connector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

    public function receive(
        Request $request,
        Connector $connector,
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
        $hasObservationList = is_array(data_get($payload, 'data.observations'))
            && data_get($payload, 'data.observations') !== [];
        $isExpandedRecord = is_string($payload['client_mac'] ?? null)
            && is_array($payload['raw'] ?? null);
        if (! $hasObservationList && ! $isExpandedRecord) {
            $this->reject($rejections, $connector, $request, 422, 'empty_observations', 'El payload no contiene observaciones Meraki válidas.');
        }

        unset($payload['secret']);
        $now = now();
        $inserted = DB::table('meraki_webhook_batches')->insertOrIgnore([
            'id' => (string) Str::ulid(),
            'organization_id' => $connector->organization_id,
            'connector_id' => $connector->id,
            'request_hash' => hash('sha256', $request->getContent()),
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'processing_status' => 'pending',
            'attempts' => 0,
            'received_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return response()->json([
            'accepted' => true,
            'duplicate' => $inserted === 0,
        ], 200);
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
