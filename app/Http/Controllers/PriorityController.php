<?php

namespace App\Http\Controllers;

use App\Models\TicketPrioridad;
use Illuminate\Http\Request;

class PriorityController extends Controller
{
    /**
     * Listar prioridades con soporte de búsqueda, filtro de estado y métricas.
     */
    public function index(Request $request)
    {
        $query = TicketPrioridad::query()->withCount(['tickets', 'usuarios']);

        // Filtro por búsqueda de nombre o código
        $query->when($request->search, function ($q, $search) {
            $q->where(function ($sub) use ($search) {
                $sub->where('nombre', 'like', "%{$search}%")
                    ->orWhere('codigo', 'like', "%{$search}%");
            });
        });

        // Filtro por estado activo/inactivo
        if ($request->has('active') && $request->active !== '' && $request->active !== 'all') {
            $isActive = filter_var($request->active, FILTER_VALIDATE_BOOLEAN);
            $query->where('activo', $isActive);
        } elseif (!$request->boolean('all') && !$request->has('include_inactive')) {
            // Si no se solicita explícitamente incluir inactivas y no es admin, mostrar solo activas
            if (!$request->user() || $request->user()->role !== 'admin') {
                $query->where('activo', true);
            }
        }

        // Ordenar por peso de nivel descendente (mayor urgencia primero) o por id
        $query->orderBy('peso_nivel', 'desc')->orderBy('id', 'asc');

        $priorities = $query->get()->map(function ($p) {
            return [
                'id'                   => $p->id,
                'nombre'               => $p->nombre,
                'name'                 => $p->nombre,
                'codigo'               => $p->codigo,
                'code'                 => $p->codigo,
                'peso_nivel'           => (int) $p->peso_nivel,
                'weight'               => (int) $p->peso_nivel,
                'sla_horas_resolucion' => (int) $p->sla_horas_resolucion,
                'sla_hours'            => (int) $p->sla_horas_resolucion,
                'activo'               => (bool) $p->activo,
                'is_active'            => (bool) $p->activo,
                'tickets_count'        => $p->tickets_count ?? 0,
                'usuarios_count'       => $p->usuarios_count ?? 0,
                'fecha_creacion'       => $p->fecha_creacion?->toDateTimeString(),
            ];
        });

        return response()->json([
            'data' => $priorities
        ]);
    }

    /**
     * Crear una nueva prioridad de atención técnica.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre'               => 'required|string|max:50|unique:ticket_prioridad,nombre',
            'codigo'               => 'required|string|max:20|unique:ticket_prioridad,codigo',
            'peso_nivel'           => 'required|integer|min:1|max:100',
            'sla_horas_resolucion' => 'required|integer|min:1',
            'activo'               => 'nullable|boolean',
        ], [
            'nombre.required'               => 'El nombre de la prioridad es obligatorio.',
            'nombre.unique'                 => 'Ya existe una prioridad con este nombre.',
            'nombre.max'                    => 'El nombre no puede exceder los 50 caracteres.',
            'codigo.required'               => 'El código identificador es obligatorio.',
            'codigo.unique'                 => 'Ya existe una prioridad con este código identificador.',
            'codigo.max'                    => 'El código no puede exceder los 20 caracteres.',
            'peso_nivel.required'           => 'El peso o nivel jerárquico es obligatorio.',
            'peso_nivel.integer'            => 'El peso debe ser un número entero.',
            'sla_horas_resolucion.required' => 'El tiempo SLA en horas es obligatorio.',
            'sla_horas_resolucion.min'      => 'El tiempo SLA debe ser de al menos 1 hora.',
        ]);

        $prioridad = TicketPrioridad::create([
            'nombre'               => trim($validated['nombre']),
            'codigo'               => strtolower(trim($validated['codigo'])),
            'peso_nivel'           => (int) $validated['peso_nivel'],
            'sla_horas_resolucion' => (int) $validated['sla_horas_resolucion'],
            'activo'               => $request->boolean('activo', true),
        ]);

        return response()->json([
            'message' => 'Prioridad creada exitosamente.',
            'data'    => [
                'id'                   => $prioridad->id,
                'nombre'               => $prioridad->nombre,
                'name'                 => $prioridad->nombre,
                'codigo'               => $prioridad->codigo,
                'code'                 => $prioridad->codigo,
                'peso_nivel'           => (int) $prioridad->peso_nivel,
                'weight'               => (int) $prioridad->peso_nivel,
                'sla_horas_resolucion' => (int) $prioridad->sla_horas_resolucion,
                'sla_hours'            => (int) $prioridad->sla_horas_resolucion,
                'activo'               => (bool) $prioridad->activo,
                'is_active'            => (bool) $prioridad->activo,
                'tickets_count'        => 0,
                'usuarios_count'       => 0,
                'fecha_creacion'       => $prioridad->fecha_creacion?->toDateTimeString(),
            ]
        ], 201);
    }

    /**
     * Ver detalle de una prioridad específica.
     */
    public function show($id)
    {
        $prioridad = TicketPrioridad::withCount(['tickets', 'usuarios'])->findOrFail($id);

        return response()->json([
            'data' => [
                'id'                   => $prioridad->id,
                'nombre'               => $prioridad->nombre,
                'name'                 => $prioridad->nombre,
                'codigo'               => $prioridad->codigo,
                'code'                 => $prioridad->codigo,
                'peso_nivel'           => (int) $prioridad->peso_nivel,
                'weight'               => (int) $prioridad->peso_nivel,
                'sla_horas_resolucion' => (int) $prioridad->sla_horas_resolucion,
                'sla_hours'            => (int) $prioridad->sla_horas_resolucion,
                'activo'               => (bool) $prioridad->activo,
                'is_active'            => (bool) $prioridad->activo,
                'tickets_count'        => $prioridad->tickets_count ?? 0,
                'usuarios_count'       => $prioridad->usuarios_count ?? 0,
                'fecha_creacion'       => $prioridad->fecha_creacion?->toDateTimeString(),
            ]
        ]);
    }

    /**
     * Actualizar una prioridad existente.
     */
    public function update(Request $request, $id)
    {
        $prioridad = TicketPrioridad::findOrFail($id);

        $validated = $request->validate([
            'nombre'               => 'required|string|max:50|unique:ticket_prioridad,nombre,' . $prioridad->id,
            'codigo'               => 'required|string|max:20|unique:ticket_prioridad,codigo,' . $prioridad->id,
            'peso_nivel'           => 'required|integer|min:1|max:100',
            'sla_horas_resolucion' => 'required|integer|min:1',
            'activo'               => 'nullable|boolean',
        ], [
            'nombre.required'               => 'El nombre de la prioridad es obligatorio.',
            'nombre.unique'                 => 'Ya existe otra prioridad con este nombre.',
            'nombre.max'                    => 'El nombre no puede exceder los 50 caracteres.',
            'codigo.required'               => 'El código identificador es obligatorio.',
            'codigo.unique'                 => 'Ya existe otra prioridad con este código identificador.',
            'codigo.max'                    => 'El código no puede exceder los 20 caracteres.',
            'peso_nivel.required'           => 'El peso o nivel jerárquico es obligatorio.',
            'sla_horas_resolucion.required' => 'El tiempo SLA en horas es obligatorio.',
            'sla_horas_resolucion.min'      => 'El tiempo SLA debe ser de al menos 1 hora.',
        ]);

        $prioridad->nombre               = trim($validated['nombre']);
        $prioridad->codigo               = strtolower(trim($validated['codigo']));
        $prioridad->peso_nivel           = (int) $validated['peso_nivel'];
        $prioridad->sla_horas_resolucion = (int) $validated['sla_horas_resolucion'];
        if ($request->has('activo')) {
            $prioridad->activo = $request->boolean('activo');
        }
        $prioridad->save();

        $prioridad->loadCount(['tickets', 'usuarios']);

        return response()->json([
            'message' => 'Prioridad actualizada exitosamente.',
            'data'    => [
                'id'                   => $prioridad->id,
                'nombre'               => $prioridad->nombre,
                'name'                 => $prioridad->nombre,
                'codigo'               => $prioridad->codigo,
                'code'                 => $prioridad->codigo,
                'peso_nivel'           => (int) $prioridad->peso_nivel,
                'weight'               => (int) $prioridad->peso_nivel,
                'sla_horas_resolucion' => (int) $prioridad->sla_horas_resolucion,
                'sla_hours'            => (int) $prioridad->sla_horas_resolucion,
                'activo'               => (bool) $prioridad->activo,
                'is_active'            => (bool) $prioridad->activo,
                'tickets_count'        => $prioridad->tickets_count ?? 0,
                'usuarios_count'       => $prioridad->usuarios_count ?? 0,
                'fecha_creacion'       => $prioridad->fecha_creacion?->toDateTimeString(),
            ]
        ]);
    }

    /**
     * Alternar estado activo/inactivo de la prioridad.
     */
    public function toggleActive($id)
    {
        $prioridad = TicketPrioridad::withCount(['tickets', 'usuarios'])->findOrFail($id);
        $prioridad->activo = !$prioridad->activo;
        $prioridad->save();

        $estadoLabel = $prioridad->activo ? 'activada' : 'desactivada (archivada)';

        return response()->json([
            'message'   => "Prioridad {$estadoLabel} correctamente.",
            'is_active' => (bool) $prioridad->activo,
            'data'      => [
                'id'                   => $prioridad->id,
                'nombre'               => $prioridad->nombre,
                'name'                 => $prioridad->nombre,
                'codigo'               => $prioridad->codigo,
                'code'                 => $prioridad->codigo,
                'peso_nivel'           => (int) $prioridad->peso_nivel,
                'weight'               => (int) $prioridad->peso_nivel,
                'sla_horas_resolucion' => (int) $prioridad->sla_horas_resolucion,
                'sla_hours'            => (int) $prioridad->sla_horas_resolucion,
                'activo'               => (bool) $prioridad->activo,
                'is_active'            => (bool) $prioridad->activo,
                'tickets_count'        => $prioridad->tickets_count ?? 0,
                'usuarios_count'       => $prioridad->usuarios_count ?? 0,
                'fecha_creacion'       => $prioridad->fecha_creacion?->toDateTimeString(),
            ]
        ]);
    }

    /**
     * Eliminar prioridad (con protección de integridad referencial histórica).
     */
    public function destroy($id)
    {
        $prioridad = TicketPrioridad::withCount('tickets')->findOrFail($id);

        if ($prioridad->tickets_count > 0) {
            return response()->json([
                'message'       => "No es posible eliminar la prioridad '{$prioridad->nombre}' porque tiene {$prioridad->tickets_count} ticket(s) asociado(s). Para preservar el historial y cálculo de SLAs, por favor desactívala.",
                'error'         => 'priority_has_tickets',
                'tickets_count' => $prioridad->tickets_count,
            ], 422);
        }

        // Desvincular de técnicos en usuario_prioridad si los hubiera
        $prioridad->usuarios()->detach();

        $prioridad->delete();

        return response()->json([
            'message' => "Prioridad '{$prioridad->nombre}' eliminada correctamente."
        ]);
    }
}
