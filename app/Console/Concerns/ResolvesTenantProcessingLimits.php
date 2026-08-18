<?php

declare(strict_types=1);

namespace App\Console\Concerns;

use App\Models\Organization;
use App\Tenancy\OrganizationContext;
use Illuminate\Support\Collection;

trait ResolvesTenantProcessingLimits
{
    private function optionalIntegerOption(string $name, int $minimum, int $maximum): ?int
    {
        $raw = $this->option($name);
        if ($raw === null || $raw === '') {
            return null;
        }

        $value = filter_var($raw, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => $minimum, 'max_range' => $maximum],
        ]);
        if ($value === false) {
            $this->error("--{$name} debe ser un entero entre {$minimum} y {$maximum}.");

            return -1;
        }

        return $value;
    }

    /** @return Collection<string, int> */
    private function tenantLimits(string $column, int $default, ?int $override = null): Collection
    {
        $query = Organization::query()->where('active', true);
        if ($organizationId = app(OrganizationContext::class)->id()) {
            $query->whereKey($organizationId);
        }

        return $query
            ->orderBy('id')
            ->pluck($column, 'id')
            ->map(fn (mixed $value): int => $override ?? max(1, (int) ($value ?? $default)));
    }
}
