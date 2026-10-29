<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

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

        $datos = $validator->validated();
        $items = $datos['productos'];

        $usuarioId = $request->attributes->get('usuario_id');

        if (!$usuarioId) {
            return response()->json([
                'message' => 'No se pudo identificar al usuario autenticado',
            ], 401);
        }

        try {

            $resultado = DB::transaction(function () use ($items, $usuarioId) {

                $total = '0.00';
                $detalles = [];
                $fallos = [];

                $productoIds = collect($items)
                    ->pluck('producto_id')
                    ->sort()
                    ->values()
                    ->all();

                $productos = DB::table('productos')
                    ->whereIn('id', $productoIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($items as $item) {

                    $productoId = (int) $item['producto_id'];
                    $cantidad = (int) $item['cantidad'];

                    $producto = $productos->get($productoId);

                    if (!$producto) {
                        $fallos[] = [
                            'producto_id' => $productoId,
                            'motivo' => 'Producto no encontrado',
                        ];

                        continue;
                    }

                    if ($producto->stock < $cantidad) {
                        $fallos[] = [
                            'producto_id' => $productoId,
                            'producto' => $producto->nombre,
                            'cantidad_solicitada' => $cantidad,
                            'stock_disponible' => $producto->stock,
                            'motivo' => 'Stock insuficiente',
                        ];
                    }
                }

                if (!empty($fallos)) {
                    return [
                        'exito' => false,
                        'fallos' => $fallos,
                    ];
                }

                foreach ($items as $item) {

                    $productoId = (int) $item['producto_id'];
                    $cantidad = (int) $item['cantidad'];

                    $producto = $productos->get($productoId);

                    $subtotal = bcmul(
                        (string) $producto->precio,
                        (string) $cantidad,
                        2
                    );

                    $total = bcadd(
                        $total,
                        $subtotal,
                        2
                    );

                    $detalles[] = [
                        'producto_id' => $producto->id,
                        'cantidad' => $cantidad,
                        'precio' => $producto->precio,
                    ];

                    DB::table('productos')
                        ->where('id', $producto->id)
                        ->decrement('stock', $cantidad);
                }

                $compraId = DB::table('compras')->insertGetId([
                    'usuario_id' => $usuarioId,
                    'fecha' => now(),
                    'total' => $total,
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
                    'exito' => true,
                    'compra_id' => $compraId,
                    'total' => $total,
                    'detalles' => $detalles,
                ];
            });

            if (!$resultado['exito']) {
                return response()->json([
                    'message' => 'No se pudo realizar la compra',
                    'fallos' => $resultado['fallos'],
                ], 422);
            }

            return response()->json([
                'message' => 'Compra registrada correctamente',
                'compra' => [
                    'compra_id' => $resultado['compra_id'],
                    'total' => $resultado['total'],
                    'detalles' => $resultado['detalles'],
                ],
            ], 201);

        } catch (\Throwable $e) {

            report($e);

            return response()->json([
                'message' => 'Ocurrió un error al registrar la compra',
            ], 500);
        }
    }
    public function index(Request $request)
    {
        $usuarioId = $request->attributes->get('usuario_id');

        $compras = DB::table('compras')
            ->where('usuario_id', $usuarioId)
            ->orderByDesc('fecha')
            ->get();

        return response()->json([
            'message' => 'Historial de compras',
            'compras' => $compras,
        ]);
    }

    public function show(Request $request, $id)
    {
        $usuarioId = $request->attributes->get('usuario_id');

        $compra = DB::table('compras')
            ->where('id', $id)
            ->where('usuario_id', $usuarioId)
            ->first();

        if (!$compra) {
            return response()->json([
                'message' => 'Compra no encontrada',
            ], 404);
        }

        $detalles = DB::table('detalle_compra')
            ->join(
                'productos',
                'detalle_compra.producto_id',
                '=',
                'productos.id'
            )
            ->where('detalle_compra.compra_id', $id)
            ->select(
                'detalle_compra.producto_id',
                'productos.nombre',
                'detalle_compra.cantidad',
                'detalle_compra.precio'
            )
            ->get();

        return response()->json([
            'message' => 'Detalle de la compra',
            'compra' => $compra,
            'productos' => $detalles,
        ]);
    }
}