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
            $table->unsignedInteger('interval_minutes')->nullable()->after('task');
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_command_statuses', function (Blueprint $table): void {
            $table->dropColumn('interval_minutes');
        });
    }
};
