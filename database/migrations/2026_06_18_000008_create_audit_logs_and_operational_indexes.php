<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('request_id', 36)->index();
            $table->string('method', 8);
            $table->string('route_name')->nullable();
            $table->string('path', 500);
            $table->string('action', 100);
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->unsignedSmallInteger('status_code');
            $table->string('ip_address', 45)->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::table('telemetry_events', function (Blueprint $table): void {
            $table->index(['connector_id', 'processing_status', 'received_at'], 'telemetry_connector_status_received');
        });
        Schema::table('device_installations', function (Blueprint $table): void {
            $table->index(['location_id', 'ended_at'], 'installation_location_active');
        });
        Schema::table('asset_device_assignments', function (Blueprint $table): void {
            $table->index(['tracking_strategy', 'ended_at'], 'assignment_strategy_active');
        });
    }

    public function down(): void
    {
        Schema::table('asset_device_assignments', fn (Blueprint $table) => $table->dropIndex('assignment_strategy_active'));
        Schema::table('device_installations', fn (Blueprint $table) => $table->dropIndex('installation_location_active'));
        Schema::table('telemetry_events', fn (Blueprint $table) => $table->dropIndex('telemetry_connector_status_received'));
        Schema::dropIfExists('audit_logs');
    }
};
