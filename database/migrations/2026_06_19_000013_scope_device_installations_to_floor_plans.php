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
        Schema::table('device_installations', function (Blueprint $table): void {
            $table->foreignUlid('floor_plan_id')->nullable()->after('location_id')->constrained()->nullOnDelete();
            $table->index(['floor_plan_id', 'ended_at'], 'installation_plan_active');
        });

        DB::table('floor_plans')->latest('created_at')->get(['id', 'location_id'])->each(function (object $plan): void {
            DB::table('device_installations')
                ->where('location_id', $plan->location_id)
                ->whereNull('floor_plan_id')
                ->update(['floor_plan_id' => $plan->id]);
        });
    }

    public function down(): void
    {
        Schema::table('device_installations', function (Blueprint $table): void {
            $table->dropIndex('installation_plan_active');
            $table->dropConstrainedForeignId('floor_plan_id');
        });
    }
};
