<?php

declare(strict_types=1);

namespace App\Enums;

enum ConnectorStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Disabled = 'disabled';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Active => 'Activo',
            self::Disabled => 'Deshabilitado',
            self::Error => 'Con error',
        };
    }
}
