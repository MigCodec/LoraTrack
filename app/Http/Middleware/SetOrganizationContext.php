<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Tenancy\OrganizationContext;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SetOrganizationContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $hasMemberships = $user->memberships()->exists();
        $memberships = $user->memberships()
            ->with('organization')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->get();
        if ($memberships->isEmpty()) {
            abort_if($hasMemberships, 403, 'Tu acceso a la organización ha vencido. Contacta a un administrador.');
            $organization = Organization::query()->create(['name' => $user->name, 'slug' => Str::slug($user->name).'-'.Str::lower(Str::random(6))]);
            $membership = OrganizationMembership::query()->create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role' => $user->role]);
            $membership->setRelation('organization', $organization);
            $memberships = collect([$membership]);
        }

        $membership = $memberships->firstWhere('organization_id', $request->session()->get('organization_id')) ?? $memberships->first();
        abort_unless($membership?->organization?->active, 403, 'La organización no está activa.');
        $request->session()->put('organization_id', $membership->organization_id);
        app(OrganizationContext::class)->set($membership->organization);
        $user->setRelation('currentMembership', $membership);

        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Model && isset($parameter->organization_id)) {
                abort_unless($parameter->organization_id === null || $parameter->organization_id === $membership->organization_id, 404);
            }
        }

        try {
            return $next($request);
        } finally {
            app(OrganizationContext::class)->set(null);
        }
    }
}
