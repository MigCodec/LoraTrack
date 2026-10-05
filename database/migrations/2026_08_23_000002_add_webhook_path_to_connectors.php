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
            $table->string('webhook_path', 80)->nullable()->after('contract_version');
            $table->unique('webhook_path');
        });
    }

    public function down(): void
    {
        Schema::table('connectors', function (Blueprint $table): void {
            $table->dropUnique(['webhook_path']);
            $table->dropColumn('webhook_path');
        });
    }
};
