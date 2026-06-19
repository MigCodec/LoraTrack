<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** @var list<string> */
    private array $tenantTables = [
        'connectors', 'products', 'skus', 'external_product_references', 'locations', 'assets', 'devices',
        'asset_device_assignments', 'device_installations', 'telemetry_events', 'position_estimates',
        'floor_plans', 'zones', 'signal_observations', 'alert_settings', 'alerts', 'payload_decoder_profiles',
        'calibration_runs', 'zone_alert_rules', 'zone_presence_states', 'connector_activity_logs', 'audit_logs',
    ];

    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::create('organization_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 32);
            $table->timestamps();
            $table->unique(['organization_id', 'user_id']);
        });
        Schema::create('organization_invitations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role', 32);
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });

        foreach ($this->tenantTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignUlid('organization_id')->nullable()->constrained()->cascadeOnDelete();
            });
        }

        if (DB::table('users')->exists()) {
            $organizationId = (string) Str::ulid();
            DB::table('organizations')->insert(['id' => $organizationId, 'name' => 'Organización inicial', 'slug' => 'organizacion-inicial', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
            foreach (DB::table('users')->get(['id', 'role']) as $user) {
                DB::table('organization_memberships')->insert(['organization_id' => $organizationId, 'user_id' => $user->id, 'role' => $user->role ?: 'viewer', 'created_at' => now(), 'updated_at' => now()]);
            }
            foreach ($this->tenantTables as $tableName) {
                DB::table($tableName)->whereNull('organization_id')->update(['organization_id' => $organizationId]);
            }
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tenantTables) as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropConstrainedForeignId('organization_id'));
        }
        Schema::dropIfExists('organization_invitations');
        Schema::dropIfExists('organization_memberships');
        Schema::dropIfExists('organizations');
    }
};
