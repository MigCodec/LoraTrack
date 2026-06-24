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
            $table->boolean('storage_cleanup_enabled')->default(false)->after('accent_color');
            $table->unsignedSmallInteger('telemetry_retention_days')->default(30)->after('storage_cleanup_enabled');
            $table->decimal('last_storage_utilization_percent', 5, 2)->nullable()->after('telemetry_retention_days');
            $table->timestamp('storage_checked_at')->nullable()->after('last_storage_utilization_percent');
            $table->timestamp('storage_cleanup_at')->nullable()->after('storage_checked_at');
            $table->unsignedBigInteger('storage_cleanup_deleted_events')->default(0)->after('storage_cleanup_at');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn([
                'storage_cleanup_enabled',
                'telemetry_retention_days',
                'last_storage_utilization_percent',
                'storage_checked_at',
                'storage_cleanup_at',
                'storage_cleanup_deleted_events',
            ]);
        });
    }
};
