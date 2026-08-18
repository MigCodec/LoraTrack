<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_scheduled_tasks', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('task', 100);
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('interval_minutes');
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('last_started_at')->nullable();
            $table->timestamp('last_finished_at')->nullable();
            $table->timestamp('last_succeeded_at')->nullable();
            $table->timestamp('last_failed_at')->nullable();
            $table->unsignedBigInteger('last_duration_ms')->nullable();
            $table->integer('last_exit_code')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedBigInteger('run_count')->default(0);
            $table->timestamps();
            $table->unique(['organization_id', 'task'], 'organization_scheduled_task_unique');
            $table->index(['enabled', 'next_run_at'], 'organization_scheduled_task_due');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_scheduled_tasks');
    }
};
