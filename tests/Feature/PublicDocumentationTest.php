<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class PublicDocumentationTest extends TestCase
{
    public function test_public_documentation_index_lists_stable_downloads(): void
    {
        $this->get(route('docs.index'))
            ->assertOk()
            ->assertSee('LoraTrack Documentation')
            ->assertSee('Customer Technical and User Guide')
            ->assertSee('Production Infrastructure and Operations Guide')
            ->assertSee('System Component Architecture')
            ->assertSee('Production Deployment Architecture')
            ->assertSee(route('docs.download', 'technical'), false)
            ->assertSee(route('docs.download', 'deployment'), false)
            ->assertSee(route('docs.diagram', 'system-architecture'), false)
            ->assertSee(route('docs.diagram', 'production-deployment'), false);
    }

    public function test_public_documentation_can_be_downloaded_as_pdf(): void
    {
        $response = $this->get(route('docs.download', 'technical'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertDownload('LoraTrack-Technical-Documentation.pdf');
    }

    public function test_unknown_document_is_not_exposed(): void
    {
        $this->get('/docs/../../.env/download')->assertNotFound();
        $this->get('/docs/internal/download')->assertNotFound();
        $this->get('/docs/diagrams/internal')->assertNotFound();
    }

    public function test_public_uml_diagram_can_be_viewed(): void
    {
        $this->get(route('docs.diagram', 'system-architecture'))
            ->assertOk()
            ->assertHeader('content-type', 'image/svg+xml; charset=UTF-8')
            ->assertHeader('x-content-type-options', 'nosniff');
    }
}
