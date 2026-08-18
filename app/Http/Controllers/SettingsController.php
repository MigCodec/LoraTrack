<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateOperationalSettingsRequest;
use App\Tenancy\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(OrganizationContext $context): View
    {
        return view('settings.index', ['organization' => $context->organization()]);
    }

    public function update(UpdateOperationalSettingsRequest $request, OrganizationContext $context): RedirectResponse
    {
        $organization = $context->organization();
        abort_unless($organization, 404);
        $data = $request->validated();
        $data['storage_cleanup_enabled'] = $request->boolean('storage_cleanup_enabled');
        $organization->update($data);

        return back()->with('status', 'Configuracion operativa actualizada.');
    }
}
