<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasUlids;

    protected $attributes = [
        'active' => true,
        'primary_color' => '#2563EB',
        'secondary_color' => '#0F172A',
        'accent_color' => '#14B8A6',
    ];

    protected $fillable = ['name', 'slug', 'active', 'logo_path', 'primary_color', 'secondary_color', 'accent_color'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_memberships')->withPivot(['role', 'expires_at'])->withTimestamps();
    }
}
