<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_command_statuses', function (Blueprint $table): void {
            $table->string('task', 100)->primary();
            $table->timestamp('last_started_at')->nullable();
            $table->timestamp('last_finished_at')->nullable();
            $table->timestamp('last_succeeded_at')->nullable();
            $table->timestamp('last_failed_at')->nullable();
            $table->unsignedBigInteger('last_duration_ms')->nullable();
            $table->integer('last_exit_code')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedBigInteger('run_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_command_statuses');
    }
};
