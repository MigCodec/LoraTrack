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
        'storage_cleanup_enabled' => false,
        'use_system_recommended_retention' => false,
        'telemetry_retention_days' => 30,
        'position_history_retention_days' => 30,
        'operational_log_retention_days' => 14,
        'terminal_inbox_retention_days' => 1,
    ];

    protected $fillable = [
        'name', 'slug', 'active', 'logo_path', 'primary_color', 'secondary_color', 'accent_color',
        'storage_cleanup_enabled', 'use_system_recommended_retention', 'telemetry_retention_days',
        'position_history_retention_days', 'operational_log_retention_days', 'terminal_inbox_retention_days',
        'last_storage_utilization_percent',
        'storage_checked_at', 'storage_cleanup_at', 'storage_cleanup_deleted_events',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'storage_cleanup_enabled' => 'boolean',
            'use_system_recommended_retention' => 'boolean',
            'telemetry_retention_days' => 'integer',
            'position_history_retention_days' => 'integer',
            'operational_log_retention_days' => 'integer',
            'terminal_inbox_retention_days' => 'integer',
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
