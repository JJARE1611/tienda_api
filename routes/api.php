<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

Route::post('/register', [AuthController::class, 'register']);

Route::post('/login', [AuthController::class, 'login']);

Route::get('/perfil', function (Request $request) {

    $usuarioId = $request->attributes->get('usuario_id');

    $usuario = DB::table('usuarios')
        ->select('id','nombre','email','rol')
        ->where('id', $usuarioId)
        ->first();

    if (!$usuario) {
        return response()->json([
            'message' => 'Usuario no encontrado'
        ], 404);
    }

    return response()->json([
        'usuario' => $usuario
    ]);

})->middleware('jwt.auth');

Route::get('/admin/prueba', function () {

    return response()->json([
        'message' => 'Acceso de administrador autorizado'
    ]);

})->middleware([
    'jwt.auth',
    'role:admin'
]);
