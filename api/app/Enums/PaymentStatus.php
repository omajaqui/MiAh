<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Registrado = 'registrado';
    case Anulado = 'anulado';
}
