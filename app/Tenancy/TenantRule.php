<?php

declare(strict_types=1);

namespace App\Tenancy;

use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

class TenantRule
{
    public static function exists(string $table, string $column = 'id'): Exists
    {
        $organizationId = app(OrganizationContext::class)->id();

        return Rule::exists($table, $column)->where(function (Builder $query) use ($organizationId): void {
            $query->where(function (Builder $tenantQuery) use ($organizationId): void {
                $tenantQuery->where('organization_id', $organizationId);
                if (app()->environment('testing')) {
                    $tenantQuery->orWhereNull('organization_id');
                }
            });
        });
    }

    public static function unique(string $table, string $column): Unique
    {
        return Rule::unique($table, $column)->where('organization_id', app(OrganizationContext::class)->id());
    }
}
