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
            $table->boolean('use_system_recommended_retention')->default(false)->after('storage_cleanup_enabled');
            $table->unsignedSmallInteger('position_history_retention_days')->default(30)->after('telemetry_retention_days');
            $table->unsignedSmallInteger('operational_log_retention_days')->default(14)->after('position_history_retention_days');
            $table->unsignedSmallInteger('terminal_inbox_retention_days')->default(1)->after('operational_log_retention_days');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn([
                'use_system_recommended_retention',
                'position_history_retention_days',
                'operational_log_retention_days',
                'terminal_inbox_retention_days',
            ]);
        });
    }
};
