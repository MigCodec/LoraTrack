<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('signal_observations', 'signal_org_receiver_observed')) {
            Schema::table('signal_observations', function (Blueprint $table): void {
                $table->index(
                    ['organization_id', 'receiver_identifier', 'observed_at'],
                    'signal_org_receiver_observed',
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('signal_observations', 'signal_org_receiver_observed')) {
            Schema::table('signal_observations', function (Blueprint $table): void {
                $table->dropIndex('signal_org_receiver_observed');
            });
        }
    }
};
