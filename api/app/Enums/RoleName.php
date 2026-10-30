<?php

namespace App\Enums;

enum RoleName: string
{
    case Admin = 'admin';
    case Socio = 'socio';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Socio => 'Socio',
        };
    }
}
