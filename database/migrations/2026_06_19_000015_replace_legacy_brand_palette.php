<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('organizations')
            ->where('primary_color', '#005B82')
            ->where('secondary_color', '#073B52')
            ->where('accent_color', '#78A22F')
            ->update([
                'primary_color' => '#2563EB',
                'secondary_color' => '#0F172A',
                'accent_color' => '#14B8A6',
                'updated_at' => now(),
            ]);

        DB::table('zones')->where('color', '#78A22F')->update(['color' => '#14B8A6']);
    }

    public function down(): void
    {
        // Branding changes are user-editable and must not be overwritten on rollback.
    }
};
