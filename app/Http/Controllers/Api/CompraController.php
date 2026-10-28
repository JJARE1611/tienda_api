<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class CompraController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'productos' => 'required|array|min:1',
            'productos.*.producto_id' => 'required|integer|distinct',
            'productos.*.cantidad' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos',
                'errores' => $validator->errors(),
            ], 422);
        }

        // El middleware JWT debe haber autenticado al usuario.
        $usuarioId = Auth::id();

        if (!$usuarioId) {
            return response()->json([
                'message' => 'No se pudo identificar al usuario autenticado',
            ], 401);
        }

        try {
            $resultado = DB::transaction(function () use ($request, $usuarioId) {
                $total = 0;
                $detalles = [];

                foreach ($request->productos as $item) {
                    $producto = DB::table('productos')
                        ->where('id', $item['producto_id'])
                        ->lockForUpdate()
                        ->first();

                    if (!$producto) {
                        throw new \RuntimeException(
                            'El producto con ID ' . $item['producto_id'] . ' no existe.'
                        );
                    }

                    if ($producto->stock < $item['cantidad']) {
                        throw new \RuntimeException(
                            'Stock insuficiente para el producto: ' . $producto->nombre
                        );
                    }

                    $subtotal = (float) $producto->precio * (int) $item['cantidad'];
                    $total += $subtotal;

                    $detalles[] = [
                        'producto_id' => $producto->id,
                        'cantidad' => (int) $item['cantidad'],
                        'precio' => $producto->precio,
                    ];

                    DB::table('productos')
                        ->where('id', $producto->id)
                        ->decrement('stock', (int) $item['cantidad']);
                }

                $compraId = DB::table('compras')->insertGetId([
                    'usuario_id' => $usuarioId,
                    'fecha' => now(),
                    'total' => round($total, 2),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ($detalles as $detalle) {
                    DB::table('detalle_compra')->insert([
                        'compra_id' => $compraId,
                        'producto_id' => $detalle['producto_id'],
                        'cantidad' => $detalle['cantidad'],
                        'precio' => $detalle['precio'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                return [
                    'compra_id' => $compraId,
                    'total' => round($total, 2),
                    'detalles' => $detalles,
                ];
            });

            return response()->json([
                'message' => 'Compra registrada correctamente',
                'compra' => $resultado,
            ], 201);

        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Ocurrió un error al registrar la compra',
            ], 500);
        }
    }
}