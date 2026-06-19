<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FloorPlan;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FloorPlanFileController extends Controller
{
    public function __invoke(FloorPlan $floorPlan): StreamedResponse|Response
    {
        $path = $floorPlan->drawablePath();
        abort_if($path === null, 404, 'El plano no tiene una vista previa disponible.');

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($floorPlan->disk);
        abort_unless($disk->exists($path), 404, 'El archivo del plano no existe.');

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $name = pathinfo($floorPlan->original_name, PATHINFO_FILENAME).($extension ? '.'.$extension : '');

        return $disk->response($path, $name, [
            'Cache-Control' => 'private, max-age=3600',
            'Content-Type' => $disk->mimeType($path) ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
