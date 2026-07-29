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
            'title' => 'Customer Technical and User Guide',
            'description' => 'Product capabilities, integrations, security model, operations, field commissioning, and end-user guidance.',
            'filename' => 'LoraTrack-Technical-Documentation.pdf',
        ],
        'deployment' => [
            'title' => 'Production Infrastructure and Operations Guide',
            'description' => 'Production requirements, installation, TLS, database, scheduling, backup, recovery, monitoring, and troubleshooting.',
            'filename' => 'LoraTrack-Deployment-Guide.pdf',
        ],
    ];

    /** @var array<string, array{title: string, description: string, filename: string}> */
    private const DIAGRAMS = [
        'system-architecture' => [
            'title' => 'System Component Architecture',
            'description' => 'UML component diagram of the complete customer-facing application and integration architecture.',
            'filename' => 'architecture/diagrams/system-component-diagram.svg',
        ],
        'production-deployment' => [
            'title' => 'Production Deployment Architecture',
            'description' => 'UML deployment diagram of production nodes, trust zones, services, and communication paths.',
            'filename' => 'architecture/diagrams/production-deployment-diagram.svg',
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

        $diagrams = collect(self::DIAGRAMS)->map(function (array $diagram, string $key): array {
            $path = $this->path($diagram['filename']);

            return $diagram + [
                'key' => $key,
                'available' => is_file($path),
            ];
        });

        return view('docs.index', ['documents' => $documents, 'diagrams' => $diagrams]);
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

    public function diagram(string $diagram): BinaryFileResponse
    {
        $definition = self::DIAGRAMS[$diagram] ?? null;
        abort_unless($definition !== null, Response::HTTP_NOT_FOUND);

        $path = $this->path($definition['filename']);
        abort_unless(is_file($path), Response::HTTP_NOT_FOUND);

        return response()->file($path, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
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
