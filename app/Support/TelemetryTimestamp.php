<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

class TelemetryTimestamp
{
    public static function parseProviderTime(mixed $value): ?CarbonInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value)->setTimezone(config('app.timezone'));
    }

    public static function forDisplay(?CarbonInterface $value): ?CarbonInterface
    {
        if (! $value) {
            return null;
        }

        if ($value->gt(now()->addMinutes(5))) {
            return Carbon::createFromFormat('Y-m-d H:i:s', $value->format('Y-m-d H:i:s'), 'UTC')
                ->setTimezone(config('app.timezone'));
        }

        return $value;
    }
}
