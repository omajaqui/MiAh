<?php

namespace App\Enums;

enum IdentificationType: string
{
    case Cc = 'CC';
    case Ce = 'CE';
    case Ti = 'TI';
    case Pas = 'PAS';
    case Nit = 'NIT';

    public function label(): string
    {
        return match ($this) {
            self::Cc => 'Cédula de ciudadanía',
            self::Ce => 'Cédula de extranjería',
            self::Ti => 'Tarjeta de identidad',
            self::Pas => 'Pasaporte',
            self::Nit => 'NIT',
        };
    }
}
