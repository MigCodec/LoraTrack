<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('telemetry_events', 'telemetry_organization_fk_index')) {
            Schema::table('telemetry_events', function (Blueprint $table): void {
                $table->index('organization_id', 'telemetry_organization_fk_index');
            });
        }
        if (! Schema::hasIndex('telemetry_events', 'telemetry_org_received_cleanup')) {
            Schema::table('telemetry_events', function (Blueprint $table): void {
                $table->index(['organization_id', 'received_at'], 'telemetry_org_received_cleanup');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasIndex('telemetry_events', 'telemetry_organization_fk_index')) {
            Schema::table('telemetry_events', function (Blueprint $table): void {
                $table->index('organization_id', 'telemetry_organization_fk_index');
            });
        }
        if (Schema::hasIndex('telemetry_events', 'telemetry_org_received_cleanup')) {
            Schema::table('telemetry_events', function (Blueprint $table): void {
                $table->dropIndex('telemetry_org_received_cleanup');
            });
        }
    }
};
