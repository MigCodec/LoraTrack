<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('position_estimates', function (Blueprint $table): void {
            $table->decimal('raw_x', 12, 4)->nullable()->after('x');
            $table->decimal('raw_y', 12, 4)->nullable()->after('y');
            $table->json('filter_state')->nullable()->after('evidence');
        });
    }

    public function down(): void
    {
        Schema::table('position_estimates', function (Blueprint $table): void {
            $table->dropColumn(['raw_x', 'raw_y', 'filter_state']);
        });
    }
};
