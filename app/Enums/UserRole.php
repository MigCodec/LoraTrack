<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Engineer = 'engineer';
    case Supervisor = 'supervisor';
    case Operator = 'operator';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Engineer => 'Ingeniería',
            self::Supervisor => 'Supervisor',
            self::Operator => 'Operador',
            self::Viewer => 'Consulta',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Admin => 'Conectores, usuarios, seguridad y acceso completo.',
            self::Engineer => 'Planos, anclas, calibración, decoders y diagnóstico técnico.',
            self::Supervisor => 'Activos, alertas y supervisión de la operación.',
            self::Operator => 'Alta, asignación y seguimiento diario de activos.',
            self::Viewer => 'Consulta de productos, activos, planos y mapa.',
        };
    }

    /** @return list<string> */
    public function permissions(): array
    {
        return match ($this) {
            self::Admin => ['*'],
            self::Engineer => ['dashboard.view', 'assets.view', 'maps.view', 'plans.manage', 'payload_profiles.manage', 'operations.view'],
            self::Supervisor => ['dashboard.view', 'assets.view', 'assets.manage', 'maps.view', 'alerts.manage', 'operations.view'],
            self::Operator => ['dashboard.view', 'assets.view', 'assets.manage', 'maps.view'],
            self::Viewer => ['dashboard.view', 'assets.view', 'maps.view'],
        };
    }
}
