<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telemetry_events', function (Blueprint $table): void {
            $table->unsignedSmallInteger('processing_attempts')->default(0)->after('processing_status');
            $table->index(['processing_status', 'processing_attempts', 'received_at'], 'telemetry_retry_queue');
        });
    }

    public function down(): void
    {
        Schema::table('telemetry_events', function (Blueprint $table): void {
            $table->dropIndex('telemetry_retry_queue');
            $table->dropColumn('processing_attempts');
        });
    }
};
