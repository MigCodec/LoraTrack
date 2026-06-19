<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Tenancy\OrganizationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToOrganization
{
    public function initializeBelongsToOrganization(): void
    {
        $this->mergeFillable(['organization_id']);
    }

    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function (Builder $builder): void {
            if ($organizationId = app(OrganizationContext::class)->id()) {
                $builder->where(function (Builder $query) use ($organizationId): void {
                    $query->where($query->qualifyColumn('organization_id'), $organizationId);
                    if (app()->environment('testing')) {
                        $query->orWhereNull($query->qualifyColumn('organization_id'));
                    }
                });
            }
        });
        static::creating(function ($model): void {
            $model->organization_id ??= app(OrganizationContext::class)->id();
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
