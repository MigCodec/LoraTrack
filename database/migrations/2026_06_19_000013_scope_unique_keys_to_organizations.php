<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skus', function (Blueprint $table): void {
            $table->dropUnique('skus_normalized_code_unique');
            $table->unique(['organization_id', 'normalized_code'], 'skus_org_normalized_unique');
        });
        Schema::table('assets', function (Blueprint $table): void {
            $table->dropUnique('assets_asset_tag_unique');
            $table->unique(['organization_id', 'asset_tag'], 'assets_org_tag_unique');
        });
        Schema::table('devices', function (Blueprint $table): void {
            $table->dropUnique('devices_identifier_unique');
            $table->unique(['organization_id', 'identifier'], 'devices_org_identifier_unique');
        });
    }

    public function down(): void
    {
        Schema::table('devices', fn (Blueprint $table) => $table->dropUnique('devices_org_identifier_unique'));
        Schema::table('assets', fn (Blueprint $table) => $table->dropUnique('assets_org_tag_unique'));
        Schema::table('skus', fn (Blueprint $table) => $table->dropUnique('skus_org_normalized_unique'));
    }
};
