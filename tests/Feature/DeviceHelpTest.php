<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceHelpTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_open_device_compatibility_help(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('help.devices'))
            ->assertOk()
            ->assertSee('Tracker B1000')
            ->assertSee('Matriz de compatibilidad');
    }
}
