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
            $table->unsignedSmallInteger('meraki_webhook_batch_limit')->default(100)->after('meraki_retention_days');
            $table->unsignedTinyInteger('meraki_webhook_max_attempts')->default(3)->after('meraki_webhook_batch_limit');
            $table->unsignedInteger('meraki_observation_limit')->default(100)->after('meraki_webhook_max_attempts');
            $table->unsignedSmallInteger('tti_uplink_limit')->default(10)->after('meraki_observation_limit');
            $table->unsignedSmallInteger('mqtt_message_limit')->default(10)->after('tti_uplink_limit');
            $table->unsignedTinyInteger('catalog_sync_limit')->default(1)->after('mqtt_message_limit');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn([
                'meraki_webhook_batch_limit',
                'meraki_webhook_max_attempts',
                'meraki_observation_limit',
                'tti_uplink_limit',
                'mqtt_message_limit',
                'catalog_sync_limit',
            ]);
        });
    }
};
