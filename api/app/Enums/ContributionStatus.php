<?php

namespace App\Enums;

enum ContributionStatus: string
{
    case Pendiente = 'pendiente';
    case Parcial = 'parcial';
    case Pagado = 'pagado';
    case Anulado = 'anulado';
}
