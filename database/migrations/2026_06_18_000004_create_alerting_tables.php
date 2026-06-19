<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_settings', function (Blueprint $t): void {
            $t->id();
            $t->boolean('enabled')->default(true);
            $t->json('recipients');
            $t->unsignedInteger('offline_minutes')->default(20);
            $t->decimal('minimum_confidence', 5, 4)->default(.25);
            $t->json('enabled_types')->nullable();
            $t->timestamps();
        });
        Schema::create('alerts', function (Blueprint $t): void {
            $t->ulid('id')->primary();
            $t->string('fingerprint')->unique();
            $t->string('type', 64);
            $t->string('severity', 16)->default('warning');
            $t->string('title');
            $t->text('message');
            $t->json('context')->nullable();
            $t->timestamp('detected_at');
            $t->timestamp('resolved_at')->nullable();
            $t->timestamp('notified_at')->nullable();
            $t->timestamps();
            $t->index(['resolved_at', 'notified_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('alert_settings');
    }
};
