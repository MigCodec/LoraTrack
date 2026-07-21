<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublicDocumentationController extends Controller
{
    /** @var array<string, array{title: string, description: string, filename: string}> */
    private const DOCUMENTS = [
        'technical' => [
            'title' => 'Technical Documentation and User Guide',
            'description' => 'Product architecture, domain model, integrations, security model, operations, and end-user guidance.',
            'filename' => 'LoraTrack-Technical-Documentation.pdf',
        ],
        'deployment' => [
            'title' => 'Professional Deployment and Operations Guide',
            'description' => 'Requirements, installation, configuration, TLS, databases, scheduling, backup, recovery, monitoring, and troubleshooting.',
            'filename' => 'LoraTrack-Deployment-Guide.pdf',
        ],
    ];

    public function index(): View
    {
        $documents = collect(self::DOCUMENTS)->map(function (array $document, string $key): array {
            $path = $this->path($document['filename']);

            return $document + [
                'key' => $key,
                'available' => is_file($path),
                'size' => is_file($path) ? $this->formatBytes((int) filesize($path)) : null,
            ];
        });

        return view('docs.index', ['documents' => $documents]);
    }

    public function download(string $document): BinaryFileResponse
    {
        $definition = self::DOCUMENTS[$document] ?? null;
        abort_unless($definition !== null, Response::HTTP_NOT_FOUND);

        $path = $this->path($definition['filename']);
        abort_unless(is_file($path), Response::HTTP_NOT_FOUND);

        return response()->download($path, $definition['filename'], [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function path(string $filename): string
    {
        return base_path('docs'.DIRECTORY_SEPARATOR.$filename);
    }

    private function formatBytes(int $bytes): string
    {
        return number_format($bytes / 1024, 0).' KB';
    }
}
