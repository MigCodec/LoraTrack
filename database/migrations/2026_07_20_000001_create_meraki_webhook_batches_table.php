<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meraki_webhook_batches', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('connector_id')->constrained()->cascadeOnDelete();
            $table->string('request_hash', 64);
            $table->json('payload');
            $table->string('processing_status', 32)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('processing_error')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['connector_id', 'request_hash'], 'meraki_webhook_batch_identity');
            $table->index(['processing_status', 'received_at'], 'meraki_webhook_batch_pending');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meraki_webhook_batches');
    }
};
