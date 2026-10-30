<?php

namespace App\Http\Controllers;

use App\Support\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;

    protected function success(mixed $data = null, string $message = 'Operación exitosa.', int $status = 200)
    {
        return ApiResponse::success($data, $message, $status);
    }
}
