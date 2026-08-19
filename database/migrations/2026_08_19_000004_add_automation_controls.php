<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduled_command_statuses', function (Blueprint $table): void {
            $table->timestamp('run_requested_at')->nullable()->after('interval_minutes');
        });

        Schema::create('scheduler_settings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->boolean('use_system_recommended')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduler_settings');

        Schema::table('scheduled_command_statuses', function (Blueprint $table): void {
            $table->dropColumn('run_requested_at');
        });
    }
};
