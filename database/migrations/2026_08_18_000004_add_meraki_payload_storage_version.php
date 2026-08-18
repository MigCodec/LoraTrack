<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('telemetry_events', 'payload_storage_version')) {
            Schema::table('telemetry_events', function (Blueprint $table): void {
                // Keep the column at the end so MySQL 8 can use instant ADD COLUMN
                // instead of rebuilding the multi-gigabyte telemetry table.
                $table->unsignedTinyInteger('payload_storage_version')->default(1);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('telemetry_events', 'payload_storage_version')) {
            Schema::table('telemetry_events', function (Blueprint $table): void {
                $table->dropColumn('payload_storage_version');
            });
        }
    }
};
