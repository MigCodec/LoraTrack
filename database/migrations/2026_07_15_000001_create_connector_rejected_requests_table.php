<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connector_rejected_requests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('connector_id')->constrained()->cascadeOnDelete();
            $table->uuid('request_id');
            $table->unsignedSmallInteger('http_status');
            $table->string('reason', 64);
            $table->string('method', 12);
            $table->string('content_type', 128)->nullable();
            $table->string('declared_version', 32)->nullable();
            $table->string('declared_type', 64)->nullable();
            $table->string('source_ip_hash', 64)->nullable();
            $table->json('context')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['connector_id', 'occurred_at']);
            $table->unique(['connector_id', 'request_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connector_rejected_requests');
    }
};
