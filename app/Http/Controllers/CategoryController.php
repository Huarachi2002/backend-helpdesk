<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Listar categorías con soporte de búsqueda, filtro de estado y métricas.
     */
    public function index(Request $request)
    {
        $query = Categoria::query()->withCount(['tickets', 'usuarios']);

        // Filtro por búsqueda de nombre o descripción
        $query->when($request->search, function ($q, $search) {
            $q->where(function ($sub) use ($search) {
                $sub->where('nombre', 'like', "%{$search}%")
                    ->orWhere('descripcion', 'like', "%{$search}%");
            });
        });

        // Filtro por estado activo/inactivo
        if ($request->has('active') && $request->active !== '' && $request->active !== 'all') {
            $isActive = filter_var($request->active, FILTER_VALIDATE_BOOLEAN);
            $query->where('activo', $isActive);
        } elseif (!$request->boolean('all') && !$request->has('include_inactive')) {
            // Si no se pide explícitamente incluir inactivas y no es búsqueda administrativa con 'all'
            // dejamos que los selects regulares solo muestren activas
            if (!$request->user() || $request->user()->role !== 'admin') {
                $query->where('activo', true);
            }
        }

        $query->orderBy('nombre', 'asc');

        $categories = $query->get()->map(function ($c) {
            return [
                'id'             => $c->id,
                'name'           => $c->nombre,
                'nombre'         => $c->nombre,
                'description'    => $c->descripcion,
                'descripcion'    => $c->descripcion,
                'activo'         => (bool) $c->activo,
                'is_active'      => (bool) $c->activo,
                'tickets_count'  => $c->tickets_count ?? 0,
                'usuarios_count' => $c->usuarios_count ?? 0,
                'fecha_creacion' => $c->fecha_creacion?->toDateTimeString(),
            ];
        });

        return response()->json([
            'data' => $categories
        ]);
    }

    /**
     * Crear una nueva categoría de soporte.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre'      => 'required|string|max:100|unique:categorias,nombre',
            'descripcion' => 'nullable|string',
            'activo'      => 'nullable|boolean',
        ], [
            'nombre.required' => 'El nombre de la categoría es obligatorio.',
            'nombre.unique'   => 'Ya existe una categoría con este nombre.',
            'nombre.max'      => 'El nombre no puede exceder los 100 caracteres.',
        ]);

        $categoria = Categoria::create([
            'nombre'      => trim($validated['nombre']),
            'descripcion' => isset($validated['descripcion']) ? trim($validated['descripcion']) : null,
            'activo'      => $request->boolean('activo', true),
        ]);

        return response()->json([
            'message' => 'Categoría creada exitosamente.',
            'data'    => [
                'id'             => $categoria->id,
                'name'           => $categoria->nombre,
                'nombre'         => $categoria->nombre,
                'description'    => $categoria->descripcion,
                'descripcion'    => $categoria->descripcion,
                'activo'         => (bool) $categoria->activo,
                'is_active'      => (bool) $categoria->activo,
                'tickets_count'  => 0,
                'usuarios_count' => 0,
                'fecha_creacion' => $categoria->fecha_creacion?->toDateTimeString(),
            ]
        ], 201);
    }

    /**
     * Ver detalle de una categoría específica.
     */
    public function show($id)
    {
        $categoria = Categoria::withCount(['tickets', 'usuarios'])->findOrFail($id);

        return response()->json([
            'data' => [
                'id'             => $categoria->id,
                'name'           => $categoria->nombre,
                'nombre'         => $categoria->nombre,
                'description'    => $categoria->descripcion,
                'descripcion'    => $categoria->descripcion,
                'activo'         => (bool) $categoria->activo,
                'is_active'      => (bool) $categoria->activo,
                'tickets_count'  => $categoria->tickets_count ?? 0,
                'usuarios_count' => $categoria->usuarios_count ?? 0,
                'fecha_creacion' => $categoria->fecha_creacion?->toDateTimeString(),
            ]
        ]);
    }

    /**
     * Actualizar una categoría existente.
     */
    public function update(Request $request, $id)
    {
        $categoria = Categoria::findOrFail($id);

        $validated = $request->validate([
            'nombre'      => 'required|string|max:100|unique:categorias,nombre,' . $categoria->id,
            'descripcion' => 'nullable|string',
            'activo'      => 'nullable|boolean',
        ], [
            'nombre.required' => 'El nombre de la categoría es obligatorio.',
            'nombre.unique'   => 'Ya existe otra categoría con este nombre.',
            'nombre.max'      => 'El nombre no puede exceder los 100 caracteres.',
        ]);

        $categoria->nombre = trim($validated['nombre']);
        if (array_key_exists('descripcion', $validated)) {
            $categoria->descripcion = $validated['descripcion'] ? trim($validated['descripcion']) : null;
        }
        if ($request->has('activo')) {
            $categoria->activo = $request->boolean('activo');
        }
        $categoria->save();

        $categoria->loadCount(['tickets', 'usuarios']);

        return response()->json([
            'message' => 'Categoría actualizada exitosamente.',
            'data'    => [
                'id'             => $categoria->id,
                'name'           => $categoria->nombre,
                'nombre'         => $categoria->nombre,
                'description'    => $categoria->descripcion,
                'descripcion'    => $categoria->descripcion,
                'activo'         => (bool) $categoria->activo,
                'is_active'      => (bool) $categoria->activo,
                'tickets_count'  => $categoria->tickets_count ?? 0,
                'usuarios_count' => $categoria->usuarios_count ?? 0,
                'fecha_creacion' => $categoria->fecha_creacion?->toDateTimeString(),
            ]
        ]);
    }

    /**
     * Alternar estado activo/inactivo de la categoría.
     */
    public function toggleActive($id)
    {
        $categoria = Categoria::withCount(['tickets', 'usuarios'])->findOrFail($id);
        $categoria->activo = !$categoria->activo;
        $categoria->save();

        $estadoLabel = $categoria->activo ? 'activada' : 'desactivada (archivada)';

        return response()->json([
            'message'   => "Categoría {$estadoLabel} correctamente.",
            'is_active' => (bool) $categoria->activo,
            'data'      => [
                'id'             => $categoria->id,
                'name'           => $categoria->nombre,
                'nombre'         => $categoria->nombre,
                'description'    => $categoria->descripcion,
                'descripcion'    => $categoria->descripcion,
                'activo'         => (bool) $categoria->activo,
                'is_active'      => (bool) $categoria->activo,
                'tickets_count'  => $categoria->tickets_count ?? 0,
                'usuarios_count' => $categoria->usuarios_count ?? 0,
                'fecha_creacion' => $categoria->fecha_creacion?->toDateTimeString(),
            ]
        ]);
    }

    /**
     * Eliminar categoría (con protección de integridad referencial histórica).
     */
    public function destroy($id)
    {
        $categoria = Categoria::withCount('tickets')->findOrFail($id);

        if ($categoria->tickets_count > 0) {
            return response()->json([
                'message' => "No es posible eliminar la categoría '{$categoria->nombre}' porque tiene {$categoria->tickets_count} ticket(s) asociado(s). Para preservar el historial, por favor desactívala.",
                'error'   => 'category_has_tickets',
                'tickets_count' => $categoria->tickets_count,
            ], 422);
        }

        // Desvincular de técnicos en usuario_categoria si los hubiera
        $categoria->usuarios()->detach();

        $categoria->delete();

        return response()->json([
            'message' => "Categoría '{$categoria->nombre}' eliminada correctamente."
        ]);
    }
}
