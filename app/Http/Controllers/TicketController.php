<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketAsignacion;
use App\Models\TicketHistory;
use App\Models\TicketPrioridad;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Resources\TicketResource;
use App\Services\TicketAssignmentService;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    public function store(StoreTicketRequest $request, TicketAssignmentService $assignmentService)
    {
        $validated = $request->validated();

        $asunto = $validated['asunto'] ?? $validated['title'];
        $descripcion = $validated['descripcion'] ?? $validated['description'];
        $catId = $validated['categoria_id'] ?? $validated['category_id'] ?? 1;

        // Determinar prioridad
        $prioridadId = $validated['prioridad_id'] ?? null;
        if (!$prioridadId) {
            $prioridadCode = $validated['priority'] ?? 'medium';
            $pModel = TicketPrioridad::whereRaw('LOWER(codigo) = ?', [strtolower($prioridadCode)])->first();
            $prioridadId = $pModel ? $pModel->id : 2;
        }

        // Generar código único (ej: TCK-105)
        $nextNumber = (Ticket::max('id') ?? 0) + 1;
        $codigo = 'TCK-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

        DB::beginTransaction();
        try {
            $ticket = Ticket::create([
                'codigo'              => $codigo,
                'asunto'              => $asunto,
                'descripcion'         => $descripcion,
                'solicitante_id'      => $request->user()->id,
                'prioridad_id'        => $prioridadId,
                'estado'              => 'EN_ESPERA',
                'tecnico_asignado_id' => null,
            ]);

            // Vincular categoría en tabla pivot
            $ticket->categorias()->attach($catId);

            $agent = $assignmentService->assign($ticket);

            if ($agent) {
                TicketAsignacion::create([
                    'ticket_id'           => $ticket->id,
                    'tecnico_id'          => $agent->id,
                    'activo'              => true,
                    'solicitud_pendiente' => false,
                    'motivo'              => 'Asignación técnica automática al crear el ticket.',
                    'fecha_inicio'        => now(),
                ]);
            }

            $comments = $agent
                ? "Ticket asignado automáticamente a {$agent->email}."
                : "Ticket derivado a la cola general (sin técnicos disponibles).";

            TicketHistory::create([
                'ticket_id'  => $ticket->id,
                'changed_by' => $request->user()->id,
                'old_status' => null,
                'new_status' => 'open',
                'comments'   => $comments
            ]);

            DB::commit();

            $ticket->load('assignee', 'categorias', 'prioridad', 'creator');

            try {
                app(\App\Services\NotificationService::class)->notifyTicketCreated($ticket);
            } catch (\Throwable $ne) {
                \Log::warning("Error al emitir notificaciones de creación de ticket: " . $ne->getMessage());
            }

            return response()->json([
                'message' => 'Ticket creado con éxito',
                'data'    => new TicketResource($ticket)
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear el ticket.',
                'error'   => $th->getMessage()
            ], 500);
        }
    }

    public function index(Request $request)
    {
        $query = Ticket::query()->with([
            'categorias',
            'prioridad',
            'assignee.rol',
            'creator.rol',
            'solicitudReasignacionPendiente.tecnico.rol',
        ]);

        // Filtro por Estado (acepta tanto 'open' como 'EN_ESPERA')
        $query->when($request->status, function ($q, $status) {
            $map = [
                'open'        => 'EN_ESPERA',
                'in_progress' => 'CLIENTE_ESPERANDO_RESPUESTA',
                'waiting'     => 'ESPERANDO_POR_CLIENTE',
                'resolved'    => 'RESUELTO',
                'closed'      => 'CERRADO',
            ];
            $dbEstado = $map[$status] ?? strtoupper($status);
            $q->where(function ($sub) use ($status, $dbEstado) {
                $sub->where('estado', $dbEstado)->orWhere('estado', $status);
            });
        });

        // Filtro por Prioridad
        $query->when($request->priority, function ($q, $priority) {
            $q->whereHas('prioridad', function ($sub) use ($priority) {
                $sub->whereRaw('LOWER(codigo) = ?', [strtolower($priority)]);
            });
        });

        // Filtro por Categoría
        $query->when($request->category_id, function ($q, $category_id) {
            $q->whereHas('categorias', fn($sub) => $sub->where('categorias.id', $category_id));
        });

        // Filtro por Técnico Asignado
        $query->when($request->assigned_to, fn($q, $assigned_to) => $q->where('tecnico_asignado_id', $assigned_to));

        // Filtros de fecha
        $query->when($request->date_from, fn($q, $date_from) => $q->whereDate('fecha_creacion', '>=', $date_from))
              ->when($request->date_to, fn($q, $date_to) => $q->whereDate('fecha_creacion', '<=', $date_to))
              ->when($request->search, function ($q, $search) {
                  $q->where(function ($sub) use ($search) {
                      $sub->where('asunto', 'like', "%{$search}%")
                          ->orWhere('descripcion', 'like', "%{$search}%")
                          ->orWhere('codigo', 'like', "%{$search}%");
                  });
              });

        $sortField = $request->get('sort_by', 'fecha_creacion');
        if ($sortField === 'created_at') $sortField = 'fecha_creacion';
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortField, $sortOrder);

        return TicketResource::collection($query->paginate(15));
    }

    public function show($id)
    {
        $ticket = Ticket::with([
            'categorias',
            'prioridad',
            'assignee.rol',
            'creator.rol',
            'history.changer.rol',
            'solicitudReasignacionPendiente.tecnico.rol',
            'asignaciones.tecnico.rol',
        ])->findOrFail($id);

        return new TicketResource($ticket);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status'   => 'required|string',
            'comments' => 'nullable|string',
        ]);

        $ticket = Ticket::findOrFail($id);
        $oldStatus = $ticket->status;

        try {
            $ticket->transitionTo($request->status, $request->user(), $request->comments);
            $ticket->load(['categorias', 'prioridad', 'assignee', 'creator', 'history.changer']);

            try {
                app(\App\Services\NotificationService::class)->notifyStatusChanged(
                    $ticket,
                    $oldStatus,
                    $ticket->status,
                    $request->user(),
                    $request->comments
                );
            } catch (\Throwable $ne) {
                \Log::warning("Error al emitir notificación de cambio de estado: " . $ne->getMessage());
            }

            return response()->json([
                'message' => 'Estado actualizado con éxito',
                'data'    => new TicketResource($ticket)
            ]);
        } catch (\App\Exceptions\InvalidStateTransitionException $e) {
            return response()->json([
                'message'             => $e->getMessage(),
                'error'               => 'invalid_state_transition',
                'current_status'      => $e->getCurrentState(),
                'target_status'       => $e->getTargetState(),
                'allowed_transitions' => $e->getAllowedTransitions(),
            ], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Lista todos los técnicos que cumplen con la prioridad y categoría del ticket.
     */
    public function eligibleAgents($id, TicketAssignmentService $assignmentService)
    {
        $ticket = Ticket::with(['categorias', 'prioridad'])->findOrFail($id);
        $eligible = $assignmentService->getEligibleAgents($ticket);

        $data = $eligible->map(function ($agent) use ($ticket) {
            return [
                'id'                  => $agent->id,
                'name'                => $agent->name,
                'nombre'              => $agent->nombre,
                'apellido'            => $agent->apellido,
                'email'               => $agent->email,
                'correo'              => $agent->correo,
                'role'                => $agent->role,
                'department'          => $agent->department,
                'tier_level'          => 2,
                'max_active_tickets'  => 10,
                'assigned_tickets_count' => $agent->assigned_tickets_count,
                'is_current_assignee' => ($agent->id === $ticket->tecnico_asignado_id),
                'allowed_priorities'  => $agent->prioridades->pluck('codigo')->map(fn($c) => strtolower($c))->toArray(),
                'categories'          => $agent->categorias->map(fn($c) => [
                    'id'   => $c->id,
                    'name' => $c->nombre,
                ]),
            ];
        });

        return response()->json([
            'ticket_id'       => $ticket->id,
            'ticket_priority' => $ticket->priority,
            'ticket_category' => $ticket->category ? [
                'id'   => $ticket->category->id,
                'name' => $ticket->category->nombre,
            ] : null,
            'data'            => $data,
        ]);
    }

    /**
     * Helper para comprobar si un usuario posee rol de Administrador / Director.
     */
    private function isUserAdmin($user): bool
    {
        if (!$user) return false;
        $rolNombre = strtoupper($user->rol?->nombre ?? '');
        return $user->role === 'admin' || in_array($rolNombre, ['DIRECTOR', 'ADMINISTRADOR', 'ADMIN']);
    }

    /**
     * Reasigna el ticket (si es Administrador/Director) o registra una solicitud de reasignación
     * (si es Técnico de Soporte) para ser autorizada por la Dirección.
     */
    public function reassign(Request $request, $id, TicketAssignmentService $assignmentService)
    {
        $request->validate([
            'assigned_to' => 'required|exists:usuarios,id',
            'comments'    => 'nullable|string',
            'motivo'      => 'nullable|string',
        ], [
            'assigned_to.required' => 'Debes seleccionar un agente de soporte para la reasignación.',
            'assigned_to.exists'   => 'El usuario seleccionado no existe en el sistema.',
        ]);

        $ticket = Ticket::with(['categorias', 'prioridad', 'assignee', 'solicitudReasignacionPendiente'])->findOrFail($id);
        $newAgent = User::with(['categorias', 'prioridades', 'rol'])->findOrFail($request->assigned_to);

        // Validar elegibilidad del nuevo técnico seleccionado
        $validation = $assignmentService->validateEligibility($newAgent, $ticket);
        if (!$validation['eligible']) {
            return response()->json([
                'message' => $validation['reason'],
                'error'   => 'ineligible_agent',
            ], 422);
        }

        $user = $request->user();
        $isAdmin = $this->isUserAdmin($user);
        $motivo = trim($request->comments ?? $request->motivo ?? '');

        // CASO 1: Si es el Administrador (Director), la reasignación se realiza DIRECTAMENTE
        if ($isAdmin) {
            $oldAssignee = $ticket->assignee;
            $oldAssigneeName = $oldAssignee ? $oldAssignee->name : 'Sin asignar';

            // Si existía una solicitud pendiente para este ticket, se cierra/actualiza
            if ($ticket->solicitudReasignacionPendiente) {
                $ticket->solicitudReasignacionPendiente->update([
                    'activo'              => false,
                    'solicitud_pendiente' => false,
                    'motivo_resolucion'   => $motivo ?: 'Reasignación ejecutada directamente por el Administrador / Director.',
                    'fecha_fin'           => now(),
                ]);
            }

            // Desactivar el técnico anterior en el historial de asignaciones
            TicketAsignacion::where('ticket_id', $ticket->id)
                ->where('activo', true)
                ->update([
                    'activo'    => false,
                    'fecha_fin' => now(),
                ]);

            // Registrar la nueva asignación activa
            TicketAsignacion::create([
                'ticket_id'           => $ticket->id,
                'tecnico_id'          => $newAgent->id,
                'activo'              => true,
                'solicitud_pendiente' => false,
                'motivo'              => $motivo ?: 'Reasignación directa por Dirección.',
                'fecha_inicio'        => now(),
            ]);

            $ticket->tecnico_asignado_id = $newAgent->id;
            $ticket->save();

            $auditComment = $motivo
                ? "Reasignación directa por Dirección: {$motivo}"
                : "Ticket reasignado directamente por la Dirección de {$oldAssigneeName} a {$newAgent->name} ({$newAgent->email}).";

            TicketHistory::create([
                'ticket_id'  => $ticket->id,
                'changed_by' => $user->id,
                'old_status' => $ticket->status,
                'new_status' => $ticket->status,
                'comments'   => $auditComment,
            ]);

            $ticket->load([
                'categorias', 'prioridad', 'assignee', 'creator', 'history.changer',
                'solicitudReasignacionPendiente.tecnico',
                'asignaciones.tecnico',
            ]);

            return response()->json([
                'message'     => "Ticket reasignado exitosamente a {$newAgent->name}.",
                'action_type' => 'direct_reassign',
                'data'        => new TicketResource($ticket)
            ]);
        }

        // CASO 2: Si es Personal de Soporte / Técnico, se genera una SOLICITUD DE REASIGNACIÓN
        if ($ticket->solicitudReasignacionPendiente) {
            return response()->json([
                'message' => 'Este ticket ya posee una solicitud de reasignación pendiente de autorización por la Dirección.',
                'error'   => 'pending_reassignment_exists',
            ], 422);
        }

        if (empty($motivo)) {
            return response()->json([
                'message' => 'Debes ingresar un motivo o justificación técnica para la solicitud de reasignación.',
                'error'   => 'motive_required',
            ], 422);
        }

        $solicitud = TicketAsignacion::create([
            'ticket_id'           => $ticket->id,
            'tecnico_id'          => $newAgent->id,
            'activo'              => false,
            'solicitud_pendiente' => true,
            'motivo'              => $motivo,
            'fecha_inicio'        => now(),
        ]);

        try {
            app(\App\Services\NotificationService::class)->notifyReassignmentRequested($ticket, $solicitud);
        } catch (\Throwable $ne) {
            \Log::warning("Error al emitir notificación de solicitud de reasignación: " . $ne->getMessage());
        }

        TicketHistory::create([
            'ticket_id'  => $ticket->id,
            'changed_by' => $user->id,
            'old_status' => $ticket->status,
            'new_status' => $ticket->status,
            'comments'   => "Solicitud de reasignación enviada por {$user->name} para derivar a {$newAgent->name}. Motivo: {$motivo}",
        ]);

        $ticket->load([
            'categorias', 'prioridad', 'assignee', 'creator', 'history.changer',
            'solicitudReasignacionPendiente.tecnico',
            'asignaciones.tecnico',
        ]);

        return response()->json([
            'message'      => "Solicitud de reasignación enviada con éxito. Pendiente de aprobación por el Administrador (Director).",
            'action_type'  => 'reassignment_requested',
            'solicitud_id' => $solicitud->id,
            'data'         => new TicketResource($ticket)
        ]);
    }

    /**
     * Aprueba una solicitud de reasignación pendiente (Exclusivo Administrador / Director).
     */
    public function approveReassignment(Request $request, $id, $requestId, TicketAssignmentService $assignmentService)
    {
        $user = $request->user();
        if (!$this->isUserAdmin($user)) {
            return response()->json([
                'message' => 'Solo el Administrador (Director) tiene permisos para autorizar la reasignación de tickets.'
            ], 403);
        }

        $ticket = Ticket::with(['categorias', 'prioridad', 'assignee'])->findOrFail($id);
        $reassignment = TicketAsignacion::where('ticket_id', $ticket->id)
            ->where('solicitud_pendiente', true)
            ->findOrFail($requestId);

        $newAgent = User::with(['categorias', 'prioridades', 'rol'])->findOrFail($reassignment->tecnico_id);
        $validation = $assignmentService->validateEligibility($newAgent, $ticket);
        if (!$validation['eligible']) {
            return response()->json([
                'message' => "El técnico propuesto ya no cumple los criterios de asignación: " . $validation['reason'],
                'error'   => 'ineligible_agent'
            ], 422);
        }

        $oldAssigneeName = $ticket->assignee ? $ticket->assignee->name : 'Sin asignar';
        $comments = trim($request->comments ?? $request->motivo ?? '');

        // 1. Desactivar la asignación anterior
        TicketAsignacion::where('ticket_id', $ticket->id)
            ->where('activo', true)
            ->update([
                'activo'    => false,
                'fecha_fin' => now(),
            ]);

        // 2. Activar la asignación aprobada
        $reassignment->update([
            'activo'              => true,
            'solicitud_pendiente' => false,
            'fecha_inicio'        => now(),
            'motivo_resolucion'   => $comments ?: 'Reasignación autorizada y aprobada por la Dirección.',
        ]);

        $ticket->tecnico_asignado_id = $newAgent->id;
        $ticket->save();

        TicketHistory::create([
            'ticket_id'  => $ticket->id,
            'changed_by' => $user->id,
            'old_status' => $ticket->status,
            'new_status' => $ticket->status,
            'comments'   => "Reasignación aprobada por el Administrador ({$user->name}). Ticket reasignado de {$oldAssigneeName} a {$newAgent->name}." . ($comments ? " Nota: {$comments}" : ''),
        ]);

        try {
            app(\App\Services\NotificationService::class)->notifyReassignmentApproved($ticket, $reassignment, $newAgent, $user);
        } catch (\Throwable $ne) {
            \Log::warning("Error al emitir notificación de aprobación de reasignación: " . $ne->getMessage());
        }

        $ticket->load([
            'categorias', 'prioridad', 'assignee', 'creator', 'history.changer',
            'solicitudReasignacionPendiente.tecnico',
            'asignaciones.tecnico',
        ]);

        return response()->json([
            'message' => "Reasignación aprobada exitosamente. El ticket ahora está asignado a {$newAgent->name}.",
            'data'    => new TicketResource($ticket)
        ]);
    }

    /**
     * Rechaza una solicitud de reasignación pendiente (Exclusivo Administrador / Director).
     */
    public function rejectReassignment(Request $request, $id, $requestId)
    {
        $user = $request->user();
        if (!$this->isUserAdmin($user)) {
            return response()->json([
                'message' => 'Solo el Administrador (Director) tiene permisos para rechazar la solicitud de reasignación.'
            ], 403);
        }

        $ticket = Ticket::with(['categorias', 'prioridad', 'assignee'])->findOrFail($id);
        $reassignment = TicketAsignacion::where('ticket_id', $ticket->id)
            ->where('solicitud_pendiente', true)
            ->findOrFail($requestId);

        $motivoRechazo = trim($request->comments ?? $request->motivo ?? $request->reason ?? 'Solicitud denegada por la Dirección.');

        $reassignment->update([
            'activo'              => false,
            'solicitud_pendiente' => false,
            'fecha_fin'           => now(),
            'motivo_resolucion'   => $motivoRechazo,
        ]);

        TicketHistory::create([
            'ticket_id'  => $ticket->id,
            'changed_by' => $user->id,
            'old_status' => $ticket->status,
            'new_status' => $ticket->status,
            'comments'   => "Solicitud de reasignación rechazada por la Dirección ({$user->name}). Motivo: {$motivoRechazo}",
        ]);

        try {
            if ($ticket->assignee) {
                app(\App\Services\NotificationService::class)->notifyReassignmentRejected($ticket, $reassignment, $ticket->assignee, $user);
            }
        } catch (\Throwable $ne) {
            \Log::warning("Error al emitir notificación de rechazo de reasignación: " . $ne->getMessage());
        }

        $ticket->load([
            'categorias', 'prioridad', 'assignee', 'creator', 'history.changer',
            'solicitudReasignacionPendiente.tecnico',
            'asignaciones.tecnico',
        ]);

        return response()->json([
            'message' => "Solicitud de reasignación rechazada.",
            'data'    => new TicketResource($ticket)
        ]);
    }

    /**
     * Lista todas las solicitudes de reasignación pendientes para el panel administrativo.
     */
    public function pendingReassignments(Request $request)
    {
        $user = $request->user();
        if (!$this->isUserAdmin($user)) {
            return response()->json([
                'message' => 'Acceso exclusivo para el Administrador / Director.'
            ], 403);
        }

        $pending = TicketAsignacion::with([
            'ticket.prioridad',
            'ticket.categorias',
            'ticket.assignee',
            'tecnico'
        ])
        ->where('solicitud_pendiente', true)
        ->orderBy('fecha_inicio', 'desc')
        ->get();

        $formatted = $pending->map(function ($p) {
            return [
                'id'                   => $p->id,
                'ticket_id'            => $p->ticket_id,
                'motivo'               => $p->motivo,
                'fecha_solicitud'      => ($p->fecha_inicio ?? $p->created_at)?->toIso8601String(),
                'estado'               => 'PENDIENTE',
                'ticket'               => $p->ticket,
                'tecnico_propuesto'    => $p->tecnico,
                'tecnicoPropuesto'     => $p->tecnico,
                'tecnico_anterior'     => $p->ticket?->assignee,
                'tecnicoAnterior'      => $p->ticket?->assignee,
                'solicitante'          => $p->ticket?->assignee,
            ];
        });

        return response()->json([
            'total' => $pending->count(),
            'data'  => $formatted
        ]);
    }
}
