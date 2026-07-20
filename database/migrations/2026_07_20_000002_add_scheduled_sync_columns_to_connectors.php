<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('connectors', function (Blueprint $table): void {
            $table->timestamp('sync_requested_at')->nullable()->after('sync_cursor')->index();
            $table->timestamp('sync_started_at')->nullable()->after('sync_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('connectors', function (Blueprint $table): void {
            $table->dropColumn(['sync_started_at', 'sync_requested_at']);
        });
    }
};
