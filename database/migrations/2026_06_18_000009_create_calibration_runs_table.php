<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calibration_runs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('floor_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('anchor_type', 32);
            $table->string('status', 32)->default('draft');
            $table->decimal('expected_x', 12, 4);
            $table->decimal('expected_y', 12, 4);
            $table->decimal('calculated_x', 12, 4);
            $table->decimal('calculated_y', 12, 4);
            $table->decimal('position_error_meters', 12, 4);
            $table->decimal('signal_rmse_meters', 12, 4);
            $table->decimal('confidence', 5, 4);
            $table->json('parameters');
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->index(['floor_plan_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calibration_runs');
    }
};
