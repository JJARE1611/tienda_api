<?php

namespace App\Http\Middleware;

use Closure;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

class JwtMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'message' => 'Token no proporcionado'
            ], 401);
        }

        try {

            $decoded = JWT::decode(
                $token,
                new Key(
                    config('jwt.secret'),
                    config('jwt.algorithm')
                )
            );

            if (!isset($decoded->sub) || !isset($decoded->rol)) {
                return response()->json([
                    'message' => 'Token inválido'
                ], 401);
            }

            $request->attributes->set(
                'usuario_id',
                (int) $decoded->sub
            );

            $request->attributes->set(
                'usuario_rol',
                (string) $decoded->rol
            );

        } catch (ExpiredException $e) {

            return response()->json([
                'message' => 'Token expirado'
            ], 401);

        } catch (LogicException | UnexpectedValueException $e) {

            return response()->json([
                'message' => 'Token inválido'
            ], 401);
        }

        return $next($request);
    }
}