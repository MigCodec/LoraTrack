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
        'meraki_retention_days' => 2,
        'storage_cleanup_threshold_percent' => 50,
        'storage_cleanup_max_events' => 10000,
    ];

    protected $fillable = [
        'name', 'slug', 'active', 'logo_path', 'primary_color', 'secondary_color', 'accent_color',
        'storage_cleanup_enabled', 'telemetry_retention_days', 'last_storage_utilization_percent',
        'meraki_retention_days', 'storage_cleanup_threshold_percent', 'storage_cleanup_max_events',
        'storage_checked_at', 'storage_cleanup_at', 'storage_cleanup_deleted_events',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'storage_cleanup_enabled' => 'boolean',
            'telemetry_retention_days' => 'integer',
            'meraki_retention_days' => 'integer',
            'storage_cleanup_threshold_percent' => 'float',
            'storage_cleanup_max_events' => 'integer',
            'last_storage_utilization_percent' => 'float',
            'storage_checked_at' => 'datetime',
            'storage_cleanup_at' => 'datetime',
            'storage_cleanup_deleted_events' => 'integer',
        ];
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
