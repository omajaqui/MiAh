<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $user = User::query()
            ->with(['role', 'person.bankType'])
            ->where('email', $request->validated('email'))
            ->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            return ApiResponse::error('Credenciales incorrectas.', 401);
        }

        if (! $user->is_active) {
            return ApiResponse::error('La cuenta de acceso está desactivada.', 403);
        }

        if ($user->person && ! $user->person->is_active) {
            return ApiResponse::error('La persona asociada a esta cuenta está inactiva.', 403);
        }

        $user->tokens()->delete();
        $token = $user->createToken('auth')->plainTextToken;

        return $this->success([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ], 'Inicio de sesión exitoso.');
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();

        return $this->success(null, 'Sesión cerrada.');
    }

    public function me(Request $request)
    {
        $user = $request->user()->load(['role', 'person.bankType']);

        return $this->success(new UserResource($user));
    }
}
