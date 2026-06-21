<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Tenancy\OrganizationContext;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class FaviconController extends Controller
{
    public function __invoke(): Response
    {
        $organization = app(OrganizationContext::class)->organization();

        if ($organization?->logo_path && Storage::disk('local')->exists($organization->logo_path)) {
            return Storage::disk('local')->response($organization->logo_path, null, [
                'Cache-Control' => 'private, no-cache, must-revalidate',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return response()->file(public_path('images/loratrack-default-logo.png'), [
            'Cache-Control' => 'public, max-age=86400',
            'Content-Type' => 'image/png',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
