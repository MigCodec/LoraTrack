<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connector_activity_logs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('connector_id')->constrained()->cascadeOnDelete();
            $table->string('level', 16)->default('info');
            $table->string('event', 64);
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['connector_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connector_activity_logs');
    }
};
