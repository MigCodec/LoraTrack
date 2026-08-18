<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'telemetry_type_status_received_connector';

    public function up(): void
    {
        if (! Schema::hasIndex('telemetry_events', self::INDEX)) {
            Schema::table('telemetry_events', function (Blueprint $table): void {
                $table->index(
                    ['event_type', 'processing_status', 'received_at', 'connector_id'],
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
