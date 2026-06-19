<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $email = mb_strtolower(trim((string) env('INITIAL_ADMIN_EMAIL', 'test@example.com')));
        $user = User::query()->updateOrCreate(['email' => $email], ['name' => env('INITIAL_ADMIN_NAME', 'Administrador'), 'role' => UserRole::Admin, 'password' => env('INITIAL_ADMIN_PASSWORD', 'password'), 'email_verified_at' => now()]);
        $organization = $user->memberships()->with('organization')->first()?->organization
            ?? Organization::query()->firstOrCreate(['slug' => 'loratrack-demo'], ['name' => env('INITIAL_ORGANIZATION_NAME', 'LoraTrack Demo'), 'active' => true]);
        $organization->memberships()->updateOrCreate(['user_id' => $user->id], ['role' => UserRole::Admin]);
    }
}
