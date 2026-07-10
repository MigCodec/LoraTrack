<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('connectors', function (Blueprint $table): void {
            $table->unsignedBigInteger('telemetry_events_count')->default(0)->after('last_error');
            $table->unsignedBigInteger('pending_events_count')->default(0)->after('telemetry_events_count');
            $table->unsignedBigInteger('processed_events_count')->default(0)->after('pending_events_count');
            $table->unsignedBigInteger('failed_events_count')->default(0)->after('processed_events_count');
        });

        DB::table('connectors')
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($connectors): void {
                foreach ($connectors as $connector) {
                    $counts = DB::table('telemetry_events')
                        ->selectRaw('COUNT(*) as total')
                        ->selectRaw("SUM(CASE WHEN processing_status = 'pending' THEN 1 ELSE 0 END) as pending")
                        ->selectRaw("SUM(CASE WHEN processing_status = 'processed' THEN 1 ELSE 0 END) as processed")
                        ->selectRaw("SUM(CASE WHEN processing_status = 'failed' THEN 1 ELSE 0 END) as failed")
                        ->where('connector_id', $connector->id)
                        ->first();

                    DB::table('connectors')
                        ->where('id', $connector->id)
                        ->update([
                            'telemetry_events_count' => (int) ($counts->total ?? 0),
                            'pending_events_count' => (int) ($counts->pending ?? 0),
                            'processed_events_count' => (int) ($counts->processed ?? 0),
                            'failed_events_count' => (int) ($counts->failed ?? 0),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('connectors', function (Blueprint $table): void {
            $table->dropColumn([
                'failed_events_count',
                'processed_events_count',
                'pending_events_count',
                'telemetry_events_count',
            ]);
        });
    }
};
