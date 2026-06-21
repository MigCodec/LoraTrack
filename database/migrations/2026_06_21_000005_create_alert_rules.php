<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_rules', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('enabled')->default(true);
            $table->string('subject_type', 32)->default('all_assets');
            $table->foreignUlid('subject_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->string('trigger_type', 32);
            $table->foreignUlid('zone_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('threshold', 12, 4)->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->unsignedInteger('cooldown_minutes')->default(5);
            $table->json('actions');
            $table->json('recipient_roles')->nullable();
            $table->json('recipient_user_ids')->nullable();
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'enabled', 'trigger_type']);
        });
        Schema::table('zone_presence_states', function (Blueprint $table): void {
            $table->timestamp('exited_at')->nullable()->after('entered_at');
        });
    }

    public function down(): void
    {
        Schema::table('zone_presence_states', fn (Blueprint $table) => $table->dropColumn('exited_at'));
        Schema::dropIfExists('alert_rules');
    }
};
