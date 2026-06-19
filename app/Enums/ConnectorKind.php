<?php

declare(strict_types=1);

namespace App\Enums;

enum ConnectorKind: string
{
    case Telemetry = 'telemetry';
    case Catalog = 'catalog';

    public function label(): string
    {
        return match ($this) {
            self::Telemetry => 'Telemetría',
            self::Catalog => 'Catálogo',
        };
    }
}
