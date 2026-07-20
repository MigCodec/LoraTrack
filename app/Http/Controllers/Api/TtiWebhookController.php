<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\ConnectorProvider;
use App\Enums\ConnectorStatus;
use App\Http\Controllers\Controller;
use App\Models\Connector;
use App\Models\TelemetryEvent;
use App\Support\TelemetryTimestamp;
use App\Tenancy\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class TtiWebhookController extends Controller
{
    public function __invoke(Request $request, Connector $connector): JsonResponse
    {
        abort_if(strlen($request->getContent()) > 1024 * 1024, 413, 'El payload excede 1 MB.');
        $requestId = is_string($request->header('X-Request-ID')) && preg_match('/^[A-Za-z0-9._-]{8,36}$/', $request->header('X-Request-ID'))
            ? $request->header('X-Request-ID')
            : (string) Str::uuid();
        abort_unless(
            $connector->provider === ConnectorProvider::TtiWebhook
            && $connector->status === ConnectorStatus::Active,
            404,
        );

        $expectedToken = (string) Arr::get($connector->credentials, 'webhook_token', '');
        $providedToken = (string) $request->bearerToken();
        abort_unless($expectedToken !== '' && hash_equals($expectedToken, $providedToken), 401);
        $context = app(OrganizationContext::class);
        $context->set($connector->organization);

        try {
            $payload = $request->validate([
                'end_device_ids' => ['required', 'array'],
                'uplink_message' => ['required', 'array'],
                'uplink_message.f_port' => ['nullable', 'integer', 'between:1,255'],
                'uplink_message.f_cnt' => ['nullable', 'integer', 'min:0'],
                'uplink_message.decoded_payload' => ['nullable', 'array'],
                'uplink_message.received_at' => ['nullable', 'date'],
                'received_at' => ['nullable', 'date'],
            ]);
            $providerReceivedAt = Arr::get($payload, 'uplink_message.received_at') ?? Arr::get($payload, 'received_at');

            $identityParts = [
                Arr::get($payload, 'end_device_ids.dev_eui'),
                Arr::get($payload, 'end_device_ids.device_id'),
                Arr::get($payload, 'uplink_message.session_key_id'),
                Arr::get($payload, 'uplink_message.f_cnt'),
                $providerReceivedAt,
            ];
            $externalEventId = hash('sha256', implode('|', array_map('strval', $identityParts)));

            $event = TelemetryEvent::query()->firstOrCreate(
                ['connector_id' => $connector->id, 'external_event_id' => $externalEventId],
                [
                    'event_type' => 'uplink',
                    'observed_at' => TelemetryTimestamp::parseProviderTime($providerReceivedAt),
                    'received_at' => now(),
                    'raw_payload' => $payload,
                    'processing_status' => 'pending',
                ],
            );

            if ($event->wasRecentlyCreated) {
                $connector->forceFill(['last_activity_at' => now(), 'last_error' => null])->save();
                $connector->logActivity('uplink_received', 'Uplink recibido desde The Things Stack y pendiente de procesamiento programado.', 'info', [
                    'event_id' => $event->id,
                    'device_id' => Arr::get($payload, 'end_device_ids.device_id'),
                    'f_port' => Arr::get($payload, 'uplink_message.f_port'),
                    'f_cnt' => Arr::get($payload, 'uplink_message.f_cnt'),
                ]);
            } else {
                $connector->logActivity('duplicate_ignored', 'Uplink duplicado de The Things Stack ignorado.', 'warning', ['event_id' => $event->id]);
            }

            return response()->json([
                'accepted' => true,
                'duplicate' => ! $event->wasRecentlyCreated,
                'event_id' => $event->id,
                'request_id' => $requestId,
            ], 202)->header('X-Request-ID', $requestId);
        } finally {
            $context->set(null);
        }
    }
}
