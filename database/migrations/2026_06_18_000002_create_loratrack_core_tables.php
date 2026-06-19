<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connectors', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('kind', 32);
            $table->string('provider', 64);
            $table->string('status', 32)->default('draft');
            $table->json('configuration')->nullable();
            $table->text('credentials')->nullable();
            $table->string('contract_version', 32)->default('1');
            $table->text('sync_cursor')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->index(['kind', 'status']);
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 32)->default('active');
            $table->timestamps();
            $table->index(['status', 'name']);
        });

        Schema::create('skus', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('product_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('normalized_code');
            $table->string('name')->nullable();
            $table->string('base_unit', 32)->nullable();
            $table->string('status', 32)->default('active');
            $table->json('attributes')->nullable();
            $table->timestamps();
            $table->unique('normalized_code');
            $table->index(['status', 'code']);
        });

        Schema::create('external_product_references', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('connector_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('sku_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('external_code')->nullable();
            $table->string('payload_checksum', 64)->nullable();
            $table->timestamp('external_updated_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->unique(['connector_id', 'external_id'], 'external_product_identity');
        });

        Schema::create('locations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('parent_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('type', 32);
            $table->string('name');
            $table->string('coordinate_system')->nullable();
            $table->decimal('origin_latitude', 10, 7)->nullable();
            $table->decimal('origin_longitude', 10, 7)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['type', 'name']);
        });

        Schema::create('assets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('sku_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('asset_tag')->unique();
            $table->string('serial_number')->nullable()->index();
            $table->string('name');
            $table->string('mobility', 32)->default('mobile');
            $table->string('status', 32)->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('devices', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('identifier')->unique();
            $table->string('name');
            $table->string('type', 32);
            $table->string('model')->nullable();
            $table->string('status', 32)->default('active');
            $table->json('metadata')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_device_assignments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('device_id')->constrained()->cascadeOnDelete();
            $table->string('tracking_strategy', 32);
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->index(['asset_id', 'ended_at']);
            $table->index(['device_id', 'ended_at']);
        });

        Schema::create('device_installations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('device_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('location_id')->constrained()->cascadeOnDelete();
            $table->decimal('x', 12, 4)->nullable();
            $table->decimal('y', 12, 4)->nullable();
            $table->decimal('z', 12, 4)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->index(['device_id', 'ended_at']);
        });

        Schema::create('telemetry_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('connector_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_event_id', 64);
            $table->string('event_type', 64)->default('uplink');
            $table->timestamp('observed_at')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->json('normalized_payload')->nullable();
            $table->json('raw_payload');
            $table->string('processing_status', 32)->default('pending');
            $table->text('processing_error')->nullable();
            $table->timestamps();
            $table->unique(['connector_id', 'external_event_id'], 'telemetry_event_identity');
            $table->index(['device_id', 'observed_at']);
            $table->index(['processing_status', 'received_at']);
        });

        Schema::create('position_estimates', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('algorithm', 64);
            $table->string('algorithm_version', 32);
            $table->decimal('x', 12, 4)->nullable();
            $table->decimal('y', 12, 4)->nullable();
            $table->decimal('z', 12, 4)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->decimal('accuracy_meters', 10, 3)->nullable();
            $table->timestamp('calculated_at');
            $table->json('evidence')->nullable();
            $table->timestamps();
            $table->index(['asset_id', 'calculated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('position_estimates');
        Schema::dropIfExists('telemetry_events');
        Schema::dropIfExists('device_installations');
        Schema::dropIfExists('asset_device_assignments');
        Schema::dropIfExists('devices');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('external_product_references');
        Schema::dropIfExists('skus');
        Schema::dropIfExists('products');
        Schema::dropIfExists('connectors');
    }
};
