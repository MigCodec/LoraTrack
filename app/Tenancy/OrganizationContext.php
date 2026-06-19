<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\Organization;

class OrganizationContext
{
    private ?Organization $organization = null;

    public function set(?Organization $organization): void
    {
        $this->organization = $organization;
    }

    public function organization(): ?Organization
    {
        return $this->organization;
    }

    public function id(): ?string
    {
        return $this->organization?->id;
    }
}
