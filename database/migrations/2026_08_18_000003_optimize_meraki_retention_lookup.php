<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'telemetry_org_type_observed_received';

    public function up(): void
    {
        if (! Schema::hasIndex('telemetry_events', self::INDEX)) {
            Schema::table('telemetry_events', function (Blueprint $table): void {
                $table->index(
                    ['organization_id', 'event_type', 'observed_at', 'received_at'],
                    self::INDEX,
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('telemetry_events', self::INDEX)) {
            Schema::table('telemetry_events', function (Blueprint $table): void {
                $table->dropIndex(self::INDEX);
            });
        }
    }
};
