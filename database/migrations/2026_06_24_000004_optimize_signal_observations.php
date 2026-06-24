<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('signal_observations', 'signal_org_transmitter_observed')) {
            Schema::table('signal_observations', function (Blueprint $table): void {
                $table->index(
                    ['organization_id', 'transmitter_mac', 'observed_at'],
                    'signal_org_transmitter_observed',
                );
            });
        }

        if (Schema::hasIndex('signal_observations', 'signal_observations_transmitter_mac_observed_at_index')) {
            Schema::table('signal_observations', function (Blueprint $table): void {
                $table->dropIndex('signal_observations_transmitter_mac_observed_at_index');
            });
        }

        if (Schema::hasIndex('signal_observations', 'signal_observations_receiver_identifier_observed_at_index')) {
            Schema::table('signal_observations', function (Blueprint $table): void {
                $table->dropIndex('signal_observations_receiver_identifier_observed_at_index');
            });
        }

        // The unique key signal_event_transmitter_receiver already starts with telemetry_event_id.
        if (Schema::hasIndex('signal_observations', 'signal_observations_event_index')) {
            Schema::table('signal_observations', function (Blueprint $table): void {
                $table->dropIndex('signal_observations_event_index');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasIndex('signal_observations', 'signal_observations_event_index')) {
            Schema::table('signal_observations', function (Blueprint $table): void {
                $table->index('telemetry_event_id', 'signal_observations_event_index');
            });
        }

        if (! Schema::hasIndex('signal_observations', 'signal_observations_receiver_identifier_observed_at_index')) {
            Schema::table('signal_observations', function (Blueprint $table): void {
                $table->index(['receiver_identifier', 'observed_at']);
            });
        }

        if (! Schema::hasIndex('signal_observations', 'signal_observations_transmitter_mac_observed_at_index')) {
            Schema::table('signal_observations', function (Blueprint $table): void {
                $table->index(['transmitter_mac', 'observed_at']);
            });
        }

        if (Schema::hasIndex('signal_observations', 'signal_org_transmitter_observed')) {
            Schema::table('signal_observations', function (Blueprint $table): void {
                $table->dropIndex('signal_org_transmitter_observed');
            });
        }
    }
};
