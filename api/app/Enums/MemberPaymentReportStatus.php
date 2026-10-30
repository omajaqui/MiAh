<?php

namespace App\Enums;

enum MemberPaymentReportStatus: string
{
    case PendienteValidar = 'pendiente_validar';
    case PagoCorrecto = 'pago_correcto';
    case PagoRechazado = 'pago_rechazado';

    public function label(): string
    {
        return match ($this) {
            self::PendienteValidar => 'Pendiente de validar',
            self::PagoCorrecto => 'Pago correcto',
            self::PagoRechazado => 'Pago rechazado',
        };
    }

    public function isPending(): bool
    {
        return $this === self::PendienteValidar;
    }
}
