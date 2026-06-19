<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zone_alert_rules', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('zone_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 16);
            $table->unsignedInteger('dwell_minutes')->nullable();
            $table->json('recipients');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['zone_id', 'event_type']);
        });

        Schema::create('zone_presence_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('zone_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('asset_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_inside')->default(false);
            $table->timestamp('entered_at')->nullable();
            $table->timestamp('last_evaluated_at')->nullable();
            $table->timestamp('dwell_notified_at')->nullable();
            $table->foreignUlid('last_position_estimate_id')->nullable()->constrained('position_estimates')->nullOnDelete();
            $table->timestamps();
            $table->unique(['zone_id', 'asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zone_presence_states');
        Schema::dropIfExists('zone_alert_rules');
    }
};
