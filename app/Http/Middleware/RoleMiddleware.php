<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(
        Request $request,
        Closure $next,
        string $rol
    ): Response {

        $rolUsuario = $request->attributes->get('usuario_rol');

        if (!$rolUsuario) {
            return response()->json([
                'message' => 'Usuario no autenticado'
            ], 401);
        }

        if ($rolUsuario !== $rol) {
            return response()->json([
                'message' => 'No tienes permisos para realizar esta acción'
            ], 403);
        }

        return $next($request);
    }
}