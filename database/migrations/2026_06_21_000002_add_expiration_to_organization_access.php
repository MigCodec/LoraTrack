<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_memberships', function (Blueprint $table): void {
            $table->timestamp('expires_at')->nullable()->after('role')->index();
        });
        Schema::table('organization_invitations', function (Blueprint $table): void {
            $table->timestamp('membership_expires_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('organization_invitations', fn (Blueprint $table) => $table->dropColumn('membership_expires_at'));
        Schema::table('organization_memberships', function (Blueprint $table): void {
            $table->dropIndex(['expires_at']);
            $table->dropColumn('expires_at');
        });
    }
};
