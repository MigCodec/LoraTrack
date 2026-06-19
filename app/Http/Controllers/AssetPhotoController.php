<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Asset;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetPhotoController extends Controller
{
    public function __invoke(Asset $asset): StreamedResponse
    {
        abort_unless($asset->photo_path && Storage::disk('local')->exists($asset->photo_path), 404);

        return Storage::disk('local')->response($asset->photo_path, null, [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
