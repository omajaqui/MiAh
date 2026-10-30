<?php

namespace App\Enums;

enum CycleStatus: string
{
    case Abierto = 'abierto';
    case EnProceso = 'en_proceso';
    case Cerrado = 'cerrado';
    case Finalizado = 'finalizado';

    public function label(): string
    {
        return match ($this) {
            self::Abierto => 'Abierto',
            self::EnProceso => 'En proceso',
            self::Cerrado => 'Cerrado',
            self::Finalizado => 'Finalizado',
        };
    }

    public function isEditable(): bool
    {
        return $this === self::Abierto;
    }

    public function allowsFinancialOperations(): bool
    {
        return $this === self::EnProceso;
    }
}
