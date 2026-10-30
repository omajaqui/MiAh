<?php

namespace App\Enums;

enum TurnStatus: string
{
    case Pendiente = 'pendiente';
    case EnCurso = 'en_curso';
    case Completado = 'completado';
    case Cancelado = 'cancelado';
}
