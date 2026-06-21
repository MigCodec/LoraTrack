<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationAccessManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_move_multiple_company_users_and_assign_an_expiration_date(): void
    {
        $admin = User::factory()->create();
        $first = User::factory()->create(['name' => 'Operador Uno']);
        $second = User::factory()->create(['name' => 'Operador Dos']);
        $organization = Organization::query()->create(['name' => 'Empresa Uno', 'slug' => 'empresa-uno']);
        $organization->memberships()->create(['user_id' => $admin->id, 'role' => UserRole::Admin]);
        $organization->memberships()->create(['user_id' => $first->id, 'role' => UserRole::Viewer]);
        $organization->memberships()->create(['user_id' => $second->id, 'role' => UserRole::Operator]);

        $this->actingAs($admin)->withSession(['organization_id' => $organization->id])
            ->patch(route('users.memberships.bulk-update'), [
                'user_ids' => [$first->id, $second->id],
                'role' => UserRole::Supervisor->value,
                'access_type' => 'until',
                'expires_at' => '2030-12-31',
            ])->assertRedirect()->assertSessionHas('status');

        $memberships = OrganizationMembership::query()->where('organization_id', $organization->id)
            ->whereIn('user_id', [$first->id, $second->id])->get();
        $this->assertCount(2, $memberships);
        $this->assertTrue($memberships->every(fn (OrganizationMembership $membership): bool => $membership->role === UserRole::Supervisor));
        $this->assertTrue($memberships->every(fn (OrganizationMembership $membership): bool => $membership->expires_at?->format('Y-m-d') === '2030-12-31'));
    }

    public function test_expired_membership_blocks_access_without_creating_another_company(): void
    {
        $user = User::factory()->create();
        $organization = Organization::query()->create(['name' => 'Empresa Vencida', 'slug' => 'empresa-vencida']);
        $organization->memberships()->create([
            'user_id' => $user->id,
            'role' => UserRole::Viewer,
            'expires_at' => now()->subMinute(),
        ]);

        $this->actingAs($user)->withSession(['organization_id' => $organization->id])
            ->get(route('dashboard'))->assertForbidden();
        $this->assertSame(1, Organization::query()->count());
    }

    public function test_company_must_keep_one_permanent_administrator(): void
    {
        $admin = User::factory()->create();
        $organization = Organization::query()->create(['name' => 'Empresa Segura', 'slug' => 'empresa-segura']);
        $organization->memberships()->create(['user_id' => $admin->id, 'role' => UserRole::Admin]);

        $this->actingAs($admin)->withSession(['organization_id' => $organization->id])
            ->put(route('users.update', $admin), [
                'role' => UserRole::Admin->value,
                'access_type' => 'until',
                'expires_at' => now()->addMonth()->toDateString(),
            ])->assertStatus(422);

        $this->assertNull($admin->memberships()->firstOrFail()->expires_at);
    }

    public function test_admin_can_remove_a_member_without_deleting_the_global_user_account(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $organization = Organization::query()->create(['name' => 'Empresa Uno', 'slug' => 'empresa-remove-member']);
        $organization->memberships()->create(['user_id' => $admin->id, 'role' => UserRole::Admin]);
        $organization->memberships()->create(['user_id' => $member->id, 'role' => UserRole::Viewer]);

        $this->actingAs($admin)->withSession(['organization_id' => $organization->id])
            ->delete(route('users.destroy', $member))->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseMissing('organization_memberships', ['organization_id' => $organization->id, 'user_id' => $member->id]);
        $this->assertDatabaseHas('users', ['id' => $member->id]);
    }

    public function test_non_admin_cannot_remove_a_company_member(): void
    {
        $viewer = User::factory()->create();
        $member = User::factory()->create();
        $organization = Organization::query()->create(['name' => 'Empresa Uno', 'slug' => 'empresa-remove-forbidden']);
        $organization->memberships()->create(['user_id' => $viewer->id, 'role' => UserRole::Viewer]);
        $organization->memberships()->create(['user_id' => $member->id, 'role' => UserRole::Operator]);

        $this->actingAs($viewer)->withSession(['organization_id' => $organization->id])
            ->delete(route('users.destroy', $member))->assertForbidden();

        $this->assertDatabaseHas('organization_memberships', ['organization_id' => $organization->id, 'user_id' => $member->id]);
    }

    public function test_users_page_lists_company_groups_and_access_status(): void
    {
        $admin = User::factory()->create();
        $expired = User::factory()->create(['name' => 'Cuenta Vencida']);
        $organization = Organization::query()->create(['name' => 'Empresa Visible', 'slug' => 'empresa-visible']);
        $organization->memberships()->create(['user_id' => $admin->id, 'role' => UserRole::Admin]);
        $organization->memberships()->create(['user_id' => $expired->id, 'role' => UserRole::Viewer, 'expires_at' => now()->subDay()]);

        $this->actingAs($admin)->withSession(['organization_id' => $organization->id])
            ->get(route('users.index'))->assertOk()
            ->assertSee('Usuarios de la empresa')
            ->assertSee('Cuenta Vencida')
            ->assertSee('Vencido')
            ->assertSee('Mover seleccionados a')
            ->assertSee('Fecha de vencimiento');
    }
}
