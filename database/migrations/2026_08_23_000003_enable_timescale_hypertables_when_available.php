<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, string> */
    private array $candidates = [
        'signal_observations' => 'observed_at',
        'telemetry_events' => 'received_at',
        'position_estimates' => 'calculated_at',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! $this->timescaleIsInstalled()) {
            return;
        }

        foreach ($this->candidates as $table => $timeColumn) {
            $this->tryCreateHypertable($table, $timeColumn);
        }
    }

    public function down(): void
    {
        // Intentionally no-op. TimescaleDB does not provide a safe, lossless
        // automatic conversion from hypertable back to a plain table.
    }

    private function timescaleIsInstalled(): bool
    {
        $result = DB::selectOne(
            "select exists(select 1 from pg_extension where extname = 'timescaledb') as installed",
        );

        return (bool) ($result?->installed ?? false);
    }

    private function tryCreateHypertable(string $table, string $timeColumn): void
    {
        DB::statement(<<<SQL
DO $$
BEGIN
    IF to_regclass('public.{$table}') IS NULL THEN
        RETURN;
    END IF;

    BEGIN
        PERFORM create_hypertable(
            '{$table}',
            '{$timeColumn}',
            if_not_exists => TRUE,
            migrate_data => TRUE
        );
    EXCEPTION
        WHEN OTHERS THEN
            RAISE NOTICE 'TimescaleDB hypertable skipped for %. Reason: %', '{$table}', SQLERRM;
    END;
END
$$;
SQL);
    }
};
