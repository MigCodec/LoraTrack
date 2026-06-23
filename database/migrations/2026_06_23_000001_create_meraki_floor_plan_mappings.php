<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('meraki_floor_plan_mappings')) {
            Schema::create('meraki_floor_plan_mappings', function (Blueprint $table): void {
                $table->ulid('id')->primary();
                $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignUlid('connector_id')->constrained()->cascadeOnDelete();
                $table->foreignUlid('floor_plan_id')->constrained()->cascadeOnDelete();
                $table->string('external_floor_plan_id');
                $table->string('external_floor_plan_name')->nullable();
                $table->boolean('invert_y')->default(true);
                $table->timestamps();
                $table->unique(['connector_id', 'external_floor_plan_id'], 'meraki_connector_floor_unique');
                $table->index(['organization_id', 'floor_plan_id']);
            });
        }

        if (! Schema::hasIndex('signal_observations', 'signal_observations_event_index')) {
            Schema::table('signal_observations', function (Blueprint $table): void {
                $table->index('telemetry_event_id', 'signal_observations_event_index');
            });
        }
        if (Schema::hasIndex('signal_observations', 'signal_event_transmitter')) {
            Schema::table('signal_observations', function (Blueprint $table): void {
                $table->dropUnique('signal_event_transmitter');
            });
        }
        if (! Schema::hasIndex('signal_observations', 'signal_event_transmitter_receiver')) {
            Schema::table('signal_observations', function (Blueprint $table): void {
                $table->unique(
                    ['telemetry_event_id', 'transmitter_mac', 'receiver_identifier'],
                    'signal_event_transmitter_receiver',
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('signal_observations', 'signal_event_transmitter_receiver')) {
            Schema::table('signal_observations', function (Blueprint $table): void {
                $table->dropUnique('signal_event_transmitter_receiver');
            });
        }
        if (! Schema::hasIndex('signal_observations', 'signal_event_transmitter')) {
            Schema::table('signal_observations', function (Blueprint $table): void {
                $table->unique(['telemetry_event_id', 'transmitter_mac'], 'signal_event_transmitter');
            });
        }
        if (Schema::hasIndex('signal_observations', 'signal_observations_event_index')) {
            Schema::table('signal_observations', function (Blueprint $table): void {
                $table->dropIndex('signal_observations_event_index');
            });
        }

        Schema::dropIfExists('meraki_floor_plan_mappings');
    }
};
