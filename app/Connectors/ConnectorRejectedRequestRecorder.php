<?php

declare(strict_types=1);

namespace App\Connectors;

use App\Models\Connector;
use App\Models\ConnectorRejectedRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConnectorRejectedRequestRecorder
{
    public const RETENTION_LIMIT = 10;

    /** @param array<string, mixed> $context */
    public function record(
        Connector $connector,
        Request $request,
        int $httpStatus,
        string $reason,
        array $context = [],
    ): ConnectorRejectedRequest {
        return DB::transaction(function () use ($connector, $request, $httpStatus, $reason, $context): ConnectorRejectedRequest {
            Connector::query()->whereKey($connector->id)->lockForUpdate()->firstOrFail();
            $payload = $request->isJson() ? $request->json()->all() : [];
            $ip = $request->ip();

            $rejection = $connector->rejectedRequests()->create([
                'organization_id' => $connector->organization_id,
                'request_id' => (string) Str::uuid(),
                'http_status' => $httpStatus,
                'reason' => $reason,
                'method' => $request->method(),
                'content_type' => mb_substr((string) $request->header('Content-Type'), 0, 128) ?: null,
                'declared_version' => $this->safeScalar($payload['version'] ?? null, 32),
                'declared_type' => $this->safeScalar($payload['type'] ?? null, 64),
                'source_ip_hash' => $ip ? hash_hmac('sha256', $ip, (string) config('app.key')) : null,
                'context' => $context === [] ? null : $context,
                'occurred_at' => now(),
            ]);

            $staleIds = $connector->rejectedRequests()
                ->latest('occurred_at')
                ->latest('id')
                ->pluck('id')
                ->slice(self::RETENTION_LIMIT);
            if ($staleIds->isNotEmpty()) {
                $connector->rejectedRequests()->whereKey($staleIds)->delete();
            }

            return $rejection;
        });
    }

    private function safeScalar(mixed $value, int $length): ?string
    {
        return is_scalar($value) ? mb_substr((string) $value, 0, $length) : null;
    }
}
