<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 32)->default('viewer')->after('email');
            $table->string('microsoft_id')->nullable()->unique()->after('role');
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['microsoft_id']);
            $table->dropColumn(['role', 'microsoft_id']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
