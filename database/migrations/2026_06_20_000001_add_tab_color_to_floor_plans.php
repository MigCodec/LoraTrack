<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('floor_plans', function (Blueprint $table): void {
            $table->string('tab_color', 7)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('floor_plans', function (Blueprint $table): void {
            $table->dropColumn('tab_color');
        });
    }
};
