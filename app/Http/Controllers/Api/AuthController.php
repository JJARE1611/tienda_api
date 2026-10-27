<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

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
}