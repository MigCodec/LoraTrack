<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuditMutations
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->requestId($request);
        $response = $next($request);
        $response->headers->set('X-Request-ID', $requestId);

        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $response;
        }

        try {
            $subject = collect($request->route()?->parameters() ?? [])->first(fn ($value) => $value instanceof Model);
            AuditLog::query()->create([
                'user_id' => $request->user()?->getAuthIdentifier(),
                'request_id' => $requestId,
                'method' => $request->method(),
                'route_name' => $request->route()?->getName(),
                'path' => $request->path(),
                'action' => $request->route()?->getActionMethod() ?? 'unknown',
                'subject_type' => $subject ? $subject::class : null,
                'subject_id' => $subject?->getKey(),
                'status_code' => $response->getStatusCode(),
                'ip_address' => $request->ip(),
                'context' => ['input_fields' => collect(array_keys($request->except(['password', 'password_confirmation', 'credentials', 'token', '_token'])))->sort()->values()->all()],
            ]);
        } catch (Throwable $exception) {
            Log::warning('No fue posible registrar la auditoría HTTP.', ['request_id' => $requestId, 'exception' => $exception::class]);
        }

        return $response;
    }

    private function requestId(Request $request): string
    {
        $provided = $request->header('X-Request-ID');

        return is_string($provided) && preg_match('/^[A-Za-z0-9._-]{8,36}$/', $provided) ? $provided : (string) Str::uuid();
    }
}
