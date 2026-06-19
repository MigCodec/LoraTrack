<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('logo_path')->nullable()->after('active');
            $table->string('primary_color', 7)->default('#2563EB')->after('logo_path');
            $table->string('secondary_color', 7)->default('#0F172A')->after('primary_color');
            $table->string('accent_color', 7)->default('#14B8A6')->after('secondary_color');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn(['logo_path', 'primary_color', 'secondary_color', 'accent_color']);
        });
    }
};
