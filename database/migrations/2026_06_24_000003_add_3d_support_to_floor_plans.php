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
            $table->string('view_mode', 8)->default('2d')->after('mime_type');
            $table->decimal('depth_meters', 10, 3)->nullable()->after('height_meters');
            $table->json('model_transform')->nullable()->after('depth_meters');
            $table->index(['organization_id', 'view_mode']);
        });
    }

    public function down(): void
    {
        Schema::table('floor_plans', function (Blueprint $table): void {
            $table->dropIndex(['organization_id', 'view_mode']);
            $table->dropColumn(['view_mode', 'depth_meters', 'model_transform']);
        });
    }
};
