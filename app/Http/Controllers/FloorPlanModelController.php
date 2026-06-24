<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FloorPlan;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FloorPlanModelController extends Controller
{
    public function __invoke(FloorPlan $floorPlan): StreamedResponse|Response
    {
        abort_unless($floorPlan->isThreeDimensional(), 404, 'El plano no contiene un modelo 3D.');

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($floorPlan->disk);
        abort_unless($disk->exists($floorPlan->file_path), 404, 'El modelo 3D no existe.');

        return $disk->response($floorPlan->file_path, $floorPlan->original_name, [
            'Cache-Control' => 'private, max-age=3600',
            'Content-Type' => $floorPlan->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
