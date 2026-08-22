<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'telemetry_meraki_payload_compaction';

    public function up(): void
    {
        Schema::table('telemetry_events', function (Blueprint $table): void {
            $table->json('raw_payload')->nullable()->change();
        });
        if (! Schema::hasColumn('telemetry_events', 'payload_storage_version')) {
            Schema::table('telemetry_events', function (Blueprint $table): void {
                $table->unsignedTinyInteger('payload_storage_version')->default(1);
            });
        }
        if (! Schema::hasIndex('telemetry_events', self::INDEX)) {
            Schema::table('telemetry_events', function (Blueprint $table): void {
                $table->index(
                    ['event_type', 'processing_status', 'payload_storage_version', 'received_at'],
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
        if (Schema::hasColumn('telemetry_events', 'payload_storage_version')) {
            Schema::table('telemetry_events', function (Blueprint $table): void {
                $table->dropColumn('payload_storage_version');
            });
        }
        // raw_payload remains nullable because processed rows may already contain NULL.
    }
};
