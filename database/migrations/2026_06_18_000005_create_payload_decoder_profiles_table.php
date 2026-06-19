<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payload_decoder_profiles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('connector_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUlid('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->boolean('enabled')->default(false);
            $table->unsignedSmallInteger('priority')->default(100);
            $table->unsignedSmallInteger('match_f_port')->nullable();
            $table->string('match_path')->nullable();
            $table->string('match_value')->nullable();
            $table->string('observations_path');
            $table->string('mac_path')->default('mac');
            $table->string('rssi_path')->default('rssi');
            $table->string('receiver_path')->nullable();
            $table->json('sample_payload')->nullable();
            $table->timestamps();
            $table->index(['enabled', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payload_decoder_profiles');
    }
};
