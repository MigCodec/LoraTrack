<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Connector;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MultiTenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_data_and_route_models_are_isolated_by_active_organization(): void
    {
        $user = User::factory()->create(['role' => UserRole::Viewer]);
        $first = Organization::query()->create(['name' => 'Empresa Uno', 'slug' => 'empresa-uno']);
        $second = Organization::query()->create(['name' => 'Empresa Dos', 'slug' => 'empresa-dos']);
        $first->memberships()->create(['user_id' => $user->id, 'role' => UserRole::Admin]);
        $second->memberships()->create(['user_id' => $user->id, 'role' => UserRole::Viewer]);
        Product::query()->create(['organization_id' => $first->id, 'name' => 'Producto empresa uno']);
        Product::query()->create(['organization_id' => $second->id, 'name' => 'Producto empresa dos']);
        $foreignConnector = Connector::query()->create(['organization_id' => $second->id, 'name' => 'Conector ajeno', 'kind' => 'telemetry', 'provider' => 'mqtt', 'status' => 'draft']);

        $this->actingAs($user)->withSession(['organization_id' => $first->id])->get(route('products.index'))
            ->assertOk()->assertSee('Producto empresa uno')->assertDontSee('Producto empresa dos');
        $this->actingAs($user)->withSession(['organization_id' => $first->id])->get(route('connectors.show', $foreignConnector))->assertNotFound();
        $this->actingAs($user)->withSession(['organization_id' => $second->id])->get(route('products.index'))
            ->assertOk()->assertSee('Producto empresa dos')->assertDontSee('Producto empresa uno');
    }

    public function test_admin_can_create_organization_with_only_name_and_admin_email(): void
    {
        Mail::fake();
        $creator = User::factory()->create(['role' => UserRole::Admin]);
        $current = Organization::query()->create(['name' => 'Actual', 'slug' => 'actual']);
        $current->memberships()->create(['user_id' => $creator->id, 'role' => UserRole::Admin]);

        $response = $this->actingAs($creator)->withSession(['organization_id' => $current->id])->post(route('organizations.store'), [
            'name' => 'Proyecto Salar', 'admin_email' => 'admin@salar.test',
        ]);

        $response->assertRedirect(route('organizations.index'))->assertSessionHas('invitation_url');
        $organization = Organization::query()->where('name', 'Proyecto Salar')->firstOrFail();
        $admin = User::query()->where('email', 'admin@salar.test')->firstOrFail();
        $this->assertDatabaseHas('organization_memberships', ['organization_id' => $organization->id, 'user_id' => $admin->id, 'role' => UserRole::Admin->value]);
        $this->assertDatabaseHas('organization_invitations', ['organization_id' => $organization->id, 'email' => 'admin@salar.test']);
    }

    public function test_company_admin_only_sees_and_updates_active_company_branding(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $first = Organization::query()->create(['name' => 'Empresa Visible', 'slug' => 'visible']);
        $second = Organization::query()->create(['name' => 'Empresa Oculta', 'slug' => 'oculta']);
        $first->memberships()->create(['user_id' => $admin->id, 'role' => UserRole::Admin]);
        $second->memberships()->create(['user_id' => $admin->id, 'role' => UserRole::Admin]);

        $this->actingAs($admin)->withSession(['organization_id' => $first->id])->get(route('organizations.index'))
            ->assertOk()->assertSee('Empresa Visible')->assertDontSee('Empresa Oculta');

        $this->actingAs($admin)->withSession(['organization_id' => $first->id])->put(route('organizations.update'), [
            'name' => 'Empresa Renovada',
            'primary_color' => '#112233',
            'secondary_color' => '#223344',
            'accent_color' => '#AABBCC',
            'logo' => UploadedFile::fake()->image('logo.png', 300, 120),
        ])->assertRedirect();

        $first->refresh();
        $this->assertSame('Empresa Renovada', $first->name);
        $this->assertSame('#112233', $first->primary_color);
        $this->assertSame('Empresa Oculta', $second->fresh()->name);
        Storage::disk('local')->assertExists($first->logo_path);
    }

    public function test_new_organization_uses_neutral_loratrack_palette(): void
    {
        $organization = Organization::query()->create(['name' => 'Nueva empresa', 'slug' => 'nueva-empresa']);

        $this->assertSame('#2563EB', $organization->primary_color);
        $this->assertSame('#0F172A', $organization->secondary_color);
        $this->assertSame('#14B8A6', $organization->accent_color);
    }

    public function test_favicon_uses_the_default_logo_without_tenant_branding(): void
    {
        $this->get(route('favicon'))
            ->assertOk()
            ->assertHeader('content-type', 'image/png');
    }

    public function test_favicon_uses_the_active_tenant_private_logo(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $organization = Organization::query()->create(['name' => 'Empresa Marca', 'slug' => 'empresa-marca']);
        $organization->memberships()->create(['user_id' => $admin->id, 'role' => UserRole::Admin]);
        $logo = UploadedFile::fake()->image('tenant-logo.png', 128, 128);
        $path = $logo->store("organizations/{$organization->id}/branding", 'local');
        $organization->update(['logo_path' => $path]);

        $faviconResponse = $this->actingAs($admin)->withSession(['organization_id' => $organization->id])
            ->get(route('favicon'))
            ->assertOk()
            ->assertHeader('content-type', 'image/png');
        $this->assertTrue($faviconResponse->baseResponse->headers->hasCacheControlDirective('private'));
        $this->assertTrue($faviconResponse->baseResponse->headers->hasCacheControlDirective('no-cache'));
        $this->assertTrue($faviconResponse->baseResponse->headers->hasCacheControlDirective('must-revalidate'));

        $this->actingAs($admin)->withSession(['organization_id' => $organization->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('favicon', ['v' => sha1($path.'|'.$organization->updated_at->timestamp)]), false);
    }
}
