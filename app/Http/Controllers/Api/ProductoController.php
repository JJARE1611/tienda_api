<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProductoController extends Controller
{
    public function index()
    {
        $productos = DB::table('productos')
            ->select(
                'id',
                'nombre',
                'descripcion',
                'precio',
                'stock',
                'created_at',
                'updated_at'
            )
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'message' => 'Lista de productos',
            'productos' => $productos
        ], 200);
    }

    public function show($id)
    {
        $producto = DB::table('productos')
            ->where('id', $id)
            ->first();

        if (!$producto) {
            return response()->json([
                'message' => 'Producto no encontrado'
            ], 404);
        }

        return response()->json([
            'producto' => $producto
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'precio' => 'required|numeric|gt:0',
            'stock' => 'required|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $ahora = now();

        $id = DB::table('productos')->insertGetId([
            'nombre' => trim($request->nombre),
            'descripcion' => $request->descripcion,
            'precio' => $request->precio,
            'stock' => $request->stock,
            'created_at' => $ahora,
            'updated_at' => $ahora
        ]);

        $producto = DB::table('productos')
            ->where('id', $id)
            ->first();

        return response()->json([
            'message' => 'Producto creado correctamente',
            'producto' => $producto
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $producto = DB::table('productos')
            ->where('id', $id)
            ->first();

        if (!$producto) {
            return response()->json([
                'message' => 'Producto no encontrado'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre' => 'sometimes|required|string|max:255',
            'descripcion' => 'sometimes|nullable|string',
            'precio' => 'sometimes|required|numeric|gt:0',
            'stock' => 'sometimes|required|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $datos = $validator->validated();

        if (empty($datos)) {
            return response()->json([
                'message' => 'Debes enviar al menos un campo para actualizar'
            ], 422);
        }

        $datos['updated_at'] = now();

        DB::table('productos')
            ->where('id', $id)
            ->update($datos);

        $productoActualizado = DB::table('productos')
            ->where('id', $id)
            ->first();

        return response()->json([
            'message' => 'Producto actualizado correctamente',
            'producto' => $productoActualizado
        ], 200);
    }

    public function destroy($id)
    {
        $producto = DB::table('productos')
            ->where('id', $id)
            ->first();

        if (!$producto) {
            return response()->json([
                'message' => 'Producto no encontrado'
            ], 404);
        }

        $tieneCompras = DB::table('detalle_compra')
            ->where('producto_id', $id)
            ->exists();

        if ($tieneCompras) {
            return response()->json([
                'message' => 'No se puede eliminar el producto porque tiene compras registradas'
            ], 409);
        }

        DB::table('productos')
            ->where('id', $id)
            ->delete();

        return response()->json([
            'message' => 'Producto eliminado correctamente'
        ], 200);
    }
}
