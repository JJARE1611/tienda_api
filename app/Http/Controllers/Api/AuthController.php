<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Firebase\JWT\JWT;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        // 1. Validar los datos recibidos
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:usuarios,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // 2. Si existen errores, detener el registro
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        // 3. Registrar usuario utilizando Query Builder
        $usuarioId = DB::table('usuarios')->insertGetId([
            'nombre' => trim($request->nombre),
            'email' => strtolower(trim($request->email)),
            'password' => Hash::make($request->password),
            'rol' => 'usuario',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 4. Respuesta
        return response()->json([
            'message' => 'Usuario registrado correctamente',
            'usuario' => [
                'id' => $usuarioId,
                'nombre' => trim($request->nombre),
                'email' => strtolower($request->email),
                'rol' => 'usuario'
            ]
        ], 201);
    }

    public function login(Request $request)
    {
        // 1. Validar los datos recibidos
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        // 2. Buscar al usuario utilizando Query Builder
        $usuario = DB::table('usuarios')
            ->where('email', strtolower(trim($request->email)))
            ->first();

        // 3. Verificar usuario y contraseña
        if (!$usuario || !Hash::check($request->password, $usuario->password)) {
            return response()->json([
                'message' => 'Credenciales incorrectas'
            ], 401);
        }

        // 4. Datos temporales del token
        $ahora = time();

        $expiracion = $ahora + (config('jwt.ttl') * 60);

        // 5. Información almacenada dentro del JWT
        $payload = [
            'iss' => config('app.url'),
            'sub' => $usuario->id,
            'email' => $usuario->email,
            'rol' => $usuario->rol,
            'iat' => $ahora,
            'exp' => $expiracion,
        ];

        $token = JWT::encode(
            $payload,
            config('jwt.secret'),
            config('jwt.algorithm')
        );

        // 7. Respuesta
        return response()->json([
            'message' => 'Inicio de sesión correcto',
            'token_type' => 'Bearer',
            'access_token' => $token,
            'expires_in' => config('jwt.ttl') * 60,
            'usuario' => [
                'id' => $usuario->id,
                'nombre' => $usuario->nombre,
                'email' => $usuario->email,
                'rol' => $usuario->rol,
            ]
        ]);
    }
}