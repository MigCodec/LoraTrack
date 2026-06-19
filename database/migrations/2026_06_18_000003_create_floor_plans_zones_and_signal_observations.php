<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('floor_plans', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('location_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('disk', 32)->default('public');
            $table->string('file_path');
            $table->string('preview_path')->nullable();
            $table->string('original_name');
            $table->string('mime_type', 128);
            $table->decimal('width_meters', 10, 3);
            $table->decimal('height_meters', 10, 3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['location_id', 'is_active']);
        });

        Schema::create('zones', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('floor_plan_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('color', 7)->default('#14B8A6');
            $table->string('shape', 32)->default('rectangle');
            $table->decimal('x_min', 10, 7);
            $table->decimal('y_min', 10, 7);
            $table->decimal('x_max', 10, 7);
            $table->decimal('y_max', 10, 7);
            $table->json('geometry')->nullable();
            $table->timestamps();
            $table->unique(['floor_plan_id', 'name']);
        });

        Schema::create('signal_observations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('telemetry_event_id')->constrained()->cascadeOnDelete();
            $table->string('transmitter_mac', 32);
            $table->string('receiver_identifier')->nullable();
            $table->smallInteger('rssi');
            $table->timestamp('observed_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['telemetry_event_id', 'transmitter_mac'], 'signal_event_transmitter');
            $table->index(['transmitter_mac', 'observed_at']);
            $table->index(['receiver_identifier', 'observed_at']);
        });

        Schema::table('device_installations', function (Blueprint $table): void {
            $table->smallInteger('reference_rssi')->default(-59)->after('longitude');
            $table->decimal('path_loss_exponent', 5, 2)->default(2.00)->after('reference_rssi');
        });

        Schema::table('position_estimates', function (Blueprint $table): void {
            $table->foreignUlid('floor_plan_id')->nullable()->after('location_id')->constrained()->nullOnDelete();
            $table->foreignUlid('zone_id')->nullable()->after('floor_plan_id')->constrained()->nullOnDelete();
            $table->foreignUlid('telemetry_event_id')->nullable()->after('zone_id')->constrained()->nullOnDelete();
            $table->unique(['asset_id', 'telemetry_event_id'], 'asset_telemetry_position');
        });
    }

    public function down(): void
    {
        Schema::table('position_estimates', function (Blueprint $table): void {
            $table->dropUnique('asset_telemetry_position');
            $table->dropConstrainedForeignId('telemetry_event_id');
            $table->dropConstrainedForeignId('zone_id');
            $table->dropConstrainedForeignId('floor_plan_id');
        });
        Schema::table('device_installations', function (Blueprint $table): void {
            $table->dropColumn(['reference_rssi', 'path_loss_exponent']);
        });
        Schema::dropIfExists('signal_observations');
        Schema::dropIfExists('zones');
        Schema::dropIfExists('floor_plans');
    }
};
