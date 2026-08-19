<?php

declare(strict_types=1);

namespace App\Telemetry;

use App\Models\Organization;

final readonly class TenantRetentionPolicy
{
    public function __construct(
        public int $telemetryDays,
        public int $positionHistoryDays,
        public int $operationalLogDays,
        public int $terminalInboxDays,
    ) {}

    public static function for(Organization $organization): self
    {
        $recommended = config('telemetry-retention.recommended');
        $values = $organization->use_system_recommended_retention
            ? $recommended
            : [
                'telemetry_days' => $organization->telemetry_retention_days,
                'position_history_days' => $organization->position_history_retention_days,
                'operational_log_days' => $organization->operational_log_retention_days,
                'terminal_inbox_days' => $organization->terminal_inbox_retention_days,
            ];

        return new self(
            telemetryDays: self::positiveDays($values['telemetry_days'] ?? 1),
            positionHistoryDays: self::positiveDays($values['position_history_days'] ?? 30),
            operationalLogDays: self::positiveDays($values['operational_log_days'] ?? 14),
            terminalInboxDays: self::positiveDays($values['terminal_inbox_days'] ?? 1),
        );
    }

    /** @return array{telemetry_days: int, position_history_days: int, operational_log_days: int, terminal_inbox_days: int} */
    public static function recommended(): array
    {
        return [
            'telemetry_days' => self::positiveDays(config('telemetry-retention.recommended.telemetry_days', 1)),
            'position_history_days' => self::positiveDays(config('telemetry-retention.recommended.position_history_days', 30)),
            'operational_log_days' => self::positiveDays(config('telemetry-retention.recommended.operational_log_days', 14)),
            'terminal_inbox_days' => self::positiveDays(config('telemetry-retention.recommended.terminal_inbox_days', 1)),
        ];
    }

    private static function positiveDays(mixed $value): int
    {
        return max(1, min(65535, (int) $value));
    }
}
