<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->unsignedSmallInteger('meraki_retention_days')->default(2)->after('telemetry_retention_days');
            $table->decimal('storage_cleanup_threshold_percent', 5, 2)->default(50)->after('meraki_retention_days');
            $table->unsignedInteger('storage_cleanup_max_events')->default(10000)->after('storage_cleanup_threshold_percent');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn([
                'meraki_retention_days',
                'storage_cleanup_threshold_percent',
                'storage_cleanup_max_events',
            ]);
        });
    }
};
