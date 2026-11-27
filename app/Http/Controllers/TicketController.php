<?php

namespace App\Http\Controllers;

use App\Models\Asignacion;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\TicketPrioridad;
use App\Models\User;
use App\Models\UsuarioAsignacion;
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

        // Determinar prioridad (PK natural: codigo)
        $prioridadCodigo = $validated['prioridad_codigo'] ?? $validated['prioridad_id'] ?? null;
        if (!$prioridadCodigo) {
            $prioridadCode = $validated['priority'] ?? 'medium';
            $pModel = TicketPrioridad::whereRaw('LOWER(codigo) = ?', [strtolower($prioridadCode)])->first();
            $prioridadCodigo = $pModel ? $pModel->codigo : 'medium';
        }

        // Generar código único atómico vía secuencia PostgreSQL (ej: TCK-026)
        $codigo = Ticket::generarSiguienteCodigo();

        DB::beginTransaction();
        try {
            $ticket = Ticket::create([
                'codigo'              => $codigo,
                'asunto'              => $asunto,
                'descripcion'         => $descripcion,
                'solicitante_ci'      => $request->user()->ci,
                'prioridad_codigo'    => $prioridadCodigo,
                'estado'              => 'EN_ESPERA',
                'tecnico_asignado_ci' => null,
            ]);

            // Vincular categoría en tabla pivot
            $ticket->categorias()->attach($catId);

            $agent = $assignmentService->assign($ticket);

            $rationale = $agent?->assignment_rationale ?? null;
            $motivo = $agent
                ? ($rationale ? "Asignación automática multi-criterio: " . $rationale['summary'] : 'Asignación técnica automática al crear el ticket.')
                : 'Ticket derivado a la cola general (sin técnicos disponibles).';

            if ($agent) {
                $now = now();
                $asignacionInicial = Asignacion::create([
                    'ticket_codigo'        => $ticket->codigo,
                    'estado'               => Asignacion::ESTADO_APROBADA,
                    'tipo_origen'          => Asignacion::TIPO_AUTOMATICA,
                    'motivo_solicitud'     => $motivo,
                    'motivo_resolucion'    => null,
                    'cargo_origen_codigo'  => null,
                    'cargo_destino_codigo' => $agent->cargo_codigo,
                    'fecha_creacion'       => $now,
                    'fecha_resolucion'     => $now,
                ]);
                $asignacionInicial->registrarParticipante(
                    $agent->ci,
                    UsuarioAsignacion::ROL_TECNICO_DESTINO,
                    $now
                );
            }

            $comments = $agent
                ? ($rationale
                    ? "Ticket asignado automáticamente a {$agent->name} ({$agent->email}). " . $rationale['summary']
                    : "Ticket asignado automáticamente a {$agent->email}.")
                : "Ticket derivado a la cola general (sin técnicos disponibles).";

            TicketHistory::create([
                'ticket_codigo'   => $ticket->codigo,
                'usuario_ci'      => $request->user()->ci,
                'estado_anterior' => null,
                'estado_nuevo'    => 'open',
                'comentarios'     => $comments,
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
            'assignee.cargo',
            'creator.rol',
            'solicitudReasignacionPendiente.participantes.usuario.rol',
            'solicitudReasignacionPendiente.participantes.usuario.cargo',
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

        // Filtro por Técnico Asignado (CI)
        $query->when($request->assigned_to, fn($q, $assigned_to) => $q->where('tecnico_asignado_ci', $assigned_to));

        // Relevamiento: tickets que llegaron al usuario actual por relevamiento o devolución
        if ($request->boolean('relevados_a_mi') && $request->user()) {
            $query->where('tecnico_asignado_ci', $request->user()->ci)
                ->whereHas('asignacionActiva', fn($q) => $q->whereIn('tipo_origen', Asignacion::TIPOS_VERTICALES));
        }

        // Relevamiento: nivel actual del ticket (nivel del cargo del responsable)
        $query->when($request->nivel, fn($q, $nivel) => $q->whereHas('assignee.cargo', fn($c) => $c->where('nivel', (int) $nivel)));

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
            'assignee.cargo',
            'creator.rol',
            'history.changer.rol',
            'solicitudReasignacionPendiente.participantes.usuario.rol',
            'solicitudReasignacionPendiente.participantes.usuario.cargo',
            'asignaciones.participantes.usuario.rol',
            'asignaciones.participantes.usuario.cargo',
            'asignaciones.cargoOrigen',
            'asignaciones.cargoDestino',
            'mensajes.usuario.rol',
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
     * Lista todos los técnicos que cumplen con la prioridad y categoría del ticket,
     * ordenados por su índice de idoneidad multi-criterio.
     */
    public function eligibleAgents($id, TicketAssignmentService $assignmentService)
    {
        $ticket = Ticket::with(['categorias', 'prioridad', 'assignee.cargo'])->findOrFail($id);
        $eligible = $assignmentService->getEligibleAgentsWithScores($ticket);

        // Reasignación horizontal: solo candidatos del mismo nivel del ticket
        $escalation = app(\App\Services\TicketEscalationService::class);
        $nivelTicket = $escalation->currentCargo($ticket)?->nivel;
        if ($nivelTicket !== null) {
            $eligible->loadMissing('cargo');
            $eligible = $eligible->filter(fn ($a) => $a->cargo && (int) $a->cargo->nivel === (int) $nivelTicket)->values();
        }

        $data = $eligible->map(function ($agent) use ($ticket) {
            return [
                'id'                     => $agent->ci,
                'ci'                     => $agent->ci,
                'name'                   => $agent->name,
                'nombre'                 => $agent->nombre,
                'apellido'               => $agent->apellido,
                'email'                  => $agent->email,
                'correo'                 => $agent->correo,
                'role'                   => $agent->role,
                'department'             => $agent->department,
                'tier_level'             => 2,
                'max_active_tickets'     => 10,
                'assigned_tickets_count' => $agent->assigned_tickets_count,
                'is_current_assignee'    => ($agent->ci === $ticket->tecnico_asignado_ci),
                'allowed_priorities'     => $agent->prioridades->pluck('codigo')->map(fn($c) => strtolower($c))->toArray(),
                'categories'             => $agent->categorias->map(fn($c) => [
                    'id'   => $c->id,
                    'name' => $c->nombre,
                ]),
                // Indicadores de scoring multi-criterio
                'assignment_score'       => $agent->assignment_score ?? null,
                'score_breakdown'        => $agent->assignment_breakdown ?? null,
                'raw_metrics'            => $agent->assignment_raw ?? null,
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
    /**
     * Aprobación jerárquica (D5): solo la cadena superior del técnico solicitante puede
     * aprobar o rechazar su solicitud de reasignación. Devuelve una respuesta 403 si no puede.
     */
    private function denyIfCannotResolve($user, Asignacion $solicitud, Ticket $ticket, string $accion)
    {
        $approval = app(\App\Services\ReassignmentApprovalService::class);
        if ($user && $approval->canResolve($user, $solicitud, $ticket)) {
            return null;
        }

        $nombres = $approval->approvers($solicitud, $ticket)->pluck('name')->join(', ', ' o ');

        return response()->json([
            'message' => "Solo el superior jerárquico del técnico solicitante puede {$accion} esta solicitud" . ($nombres ? ": {$nombres}." : '.'),
            'error'   => 'not_reassignment_approver',
        ], 403);
    }

    private function isUserAdmin($user): bool
    {
        if (!$user) return false;
        $rolNombre = strtoupper($user->rol?->nombre ?? '');
        return $user->role === 'admin' || in_array($rolNombre, ['DIRECTOR', 'ADMINISTRADOR', 'ADMIN']);
    }

    /**
     * Verifica si el ticket se encuentra en un estado final (Resuelto o Cerrado)
     * donde ya no se permiten reasignaciones.
     */
    private function isTicketClosedOrResolved(Ticket $ticket): bool
    {
        $estado = strtoupper((string) ($ticket->estado ?? ''));
        $status = strtolower((string) ($ticket->status ?? ''));

        return in_array($estado, ['RESUELTO', 'CERRADO'], true)
            || in_array($status, ['resolved', 'closed'], true);
    }

    /**
     * Reasigna el ticket (si es Administrador/Director) o registra una solicitud de reasignación
     * (si es Técnico de Soporte) para ser autorizada por la Dirección / cadena superior.
     */
    public function reassign(Request $request, $id, TicketAssignmentService $assignmentService)
    {
        $request->validate([
            'assigned_to' => 'required|string|exists:usuarios,ci',
            'comments'    => 'nullable|string',
            'motivo'      => 'nullable|string',
        ], [
            'assigned_to.required' => 'Debes seleccionar un agente de soporte para la reasignación.',
            'assigned_to.exists'   => 'El usuario seleccionado no existe en el sistema.',
        ]);

        $ticket = Ticket::with([
            'categorias',
            'prioridad',
            'assignee.cargo',
            'solicitudReasignacionPendiente.participantes',
        ])->findOrFail($id);

        if ($this->isTicketClosedOrResolved($ticket)) {
            return response()->json([
                'message' => 'No se puede reasignar un ticket que se encuentra en estado Resuelto o Cerrado.',
                'error'   => 'ticket_closed_or_resolved',
            ], 422);
        }

        $newAgent = User::with(['categorias', 'prioridades', 'rol', 'cargo'])->findOrFail($request->assigned_to);

        // Validar elegibilidad del nuevo técnico seleccionado
        $validation = $assignmentService->validateEligibility($newAgent, $ticket);
        if (!$validation['eligible']) {
            return response()->json([
                'message' => $validation['reason'],
                'error'   => 'ineligible_agent',
            ], 422);
        }

        // La reasignación es horizontal: solo dentro del nivel donde está el ticket
        if ($levelError = app(\App\Services\TicketEscalationService::class)->reassignmentLevelError($ticket, $newAgent)) {
            return response()->json([
                'message' => $levelError,
                'error'   => 'reassign_other_level',
            ], 422);
        }

        $user = $request->user();
        $isAdmin = $this->isUserAdmin($user);
        $motivo = trim($request->comments ?? $request->motivo ?? '');

        // CASO 1: Si es el Administrador (Director), la reasignación se realiza DIRECTAMENTE
        if ($isAdmin) {
            $oldAssignee = $ticket->assignee;
            $oldAssigneeName = $oldAssignee ? $oldAssignee->name : 'Sin asignar';

            DB::transaction(function () use ($ticket, $user, $oldAssignee, $newAgent, $motivo) {
                $now = now();

                // Si existía una solicitud pendiente para este ticket, se marca RECHAZADA por intervención directa
                if ($ticket->solicitudReasignacionPendiente) {
                    $pendiente = $ticket->solicitudReasignacionPendiente;
                    $pendiente->update([
                        'estado'            => Asignacion::ESTADO_RECHAZADA,
                        'motivo_resolucion' => $motivo ?: 'Reasignación ejecutada directamente por el Administrador / Director.',
                        'fecha_resolucion'  => $now,
                    ]);

                    $rolCierre = ($user->ci === $pendiente->tecnicoDestinoCi())
                        ? UsuarioAsignacion::ROL_DESTINO_RECHAZADOR
                        : UsuarioAsignacion::ROL_RECHAZADOR;
                    $pendiente->registrarParticipante($user->ci, $rolCierre, $now);
                }

                // Registrar la nueva operación de reasignación directa en `asignacion` + `usuario_asignacion`
                $nueva = Asignacion::create([
                    'ticket_codigo'        => $ticket->codigo,
                    'estado'               => Asignacion::ESTADO_APROBADA,
                    'tipo_origen'          => Asignacion::TIPO_REASIGNACION_DIRECTA,
                    'motivo_solicitud'     => $motivo ?: 'Reasignación directa por Dirección.',
                    'motivo_resolucion'    => $motivo ?: 'Reasignación directa por Dirección.',
                    'cargo_origen_codigo'  => $oldAssignee?->cargo_codigo,
                    'cargo_destino_codigo' => $newAgent->cargo_codigo,
                    'fecha_creacion'       => $now,
                    'fecha_resolucion'     => $now,
                ]);

                // Participantes respetando PK compuesta (asignacion_id, usuario_ci)
                if ($user->ci === $newAgent->ci) {
                    $nueva->registrarParticipante($user->ci, UsuarioAsignacion::ROL_DESTINO_APROBADOR, $now);
                } else {
                    $nueva->registrarParticipante($user->ci, UsuarioAsignacion::ROL_APROBADOR, $now);
                    $nueva->registrarParticipante($newAgent->ci, UsuarioAsignacion::ROL_TECNICO_DESTINO, $now);
                }

                if ($oldAssignee && $oldAssignee->ci !== $user->ci && $oldAssignee->ci !== $newAgent->ci) {
                    $nueva->registrarParticipante($oldAssignee->ci, UsuarioAsignacion::ROL_TECNICO_ORIGEN, $now);
                }

                $ticket->tecnico_asignado_ci = $newAgent->ci;
                $ticket->save();
                app(\App\Services\TicketEscalationService::class)->recalcularNivelRelevamiento($ticket->fresh(), $newAgent->cargo);
            });

            $auditComment = $motivo
                ? "Reasignación directa por Dirección: {$motivo}"
                : "Ticket reasignado directamente por la Dirección de {$oldAssigneeName} a {$newAgent->name} ({$newAgent->email}).";

            TicketHistory::create([
                'ticket_codigo'   => $ticket->codigo,
                'usuario_ci'      => $user->ci,
                'estado_anterior' => $ticket->status,
                'estado_nuevo'    => $ticket->status,
                'comentarios'     => $auditComment,
            ]);

            $ticket->refresh()->load([
                'categorias', 'prioridad', 'assignee.rol', 'assignee.cargo', 'creator.rol', 'history.changer.rol',
                'solicitudReasignacionPendiente.participantes.usuario.rol',
                'solicitudReasignacionPendiente.participantes.usuario.cargo',
                'asignaciones.participantes.usuario.rol',
                'asignaciones.participantes.usuario.cargo',
                'asignaciones.cargoOrigen',
                'asignaciones.cargoDestino',
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

        if ($newAgent->ci === $user->ci) {
            return response()->json([
                'message' => 'No puedes solicitar una reasignación hacia ti mismo.',
                'error'   => 'same_requester_and_target',
            ], 422);
        }

        if (empty($motivo)) {
            return response()->json([
                'message' => 'Debes ingresar un motivo o justificación técnica para la solicitud de reasignación.',
                'error'   => 'motive_required',
            ], 422);
        }

        $solicitud = DB::transaction(function () use ($ticket, $user, $newAgent, $motivo) {
            $now = now();
            $actual = $ticket->assignee;

            $solicitud = Asignacion::create([
                'ticket_codigo'        => $ticket->codigo,
                'estado'               => Asignacion::ESTADO_PENDIENTE,
                'tipo_origen'          => Asignacion::TIPO_SOLICITUD_REASIGNACION,
                'motivo_solicitud'     => $motivo,
                'motivo_resolucion'    => null,
                'cargo_origen_codigo'  => $actual?->cargo_codigo ?? $user->cargo_codigo,
                'cargo_destino_codigo' => $newAgent->cargo_codigo,
                'fecha_creacion'       => $now,
                'fecha_resolucion'     => null,
            ]);

            $solicitud->registrarParticipante($user->ci, UsuarioAsignacion::ROL_SOLICITANTE, $now);
            $solicitud->registrarParticipante($newAgent->ci, UsuarioAsignacion::ROL_TECNICO_DESTINO, $now);

            if ($actual && $actual->ci !== $user->ci && $actual->ci !== $newAgent->ci) {
                $solicitud->registrarParticipante($actual->ci, UsuarioAsignacion::ROL_TECNICO_ORIGEN, $now);
            }

            return $solicitud;
        });

        try {
            app(\App\Services\NotificationService::class)->notifyReassignmentRequested($ticket, $solicitud);
        } catch (\Throwable $ne) {
            \Log::warning("Error al emitir notificación de solicitud de reasignación: " . $ne->getMessage());
        }

        TicketHistory::create([
            'ticket_codigo'   => $ticket->codigo,
            'usuario_ci'      => $user->ci,
            'estado_anterior' => $ticket->status,
            'estado_nuevo'    => $ticket->status,
            'comentarios'     => "Solicitud de reasignación enviada por {$user->name} para derivar a {$newAgent->name}. Motivo: {$motivo}",
        ]);

        $ticket->refresh()->load([
            'categorias', 'prioridad', 'assignee.rol', 'assignee.cargo', 'creator.rol', 'history.changer.rol',
            'solicitudReasignacionPendiente.participantes.usuario.rol',
            'solicitudReasignacionPendiente.participantes.usuario.cargo',
            'asignaciones.participantes.usuario.rol',
            'asignaciones.participantes.usuario.cargo',
            'asignaciones.cargoOrigen',
            'asignaciones.cargoDestino',
        ]);

        return response()->json([
            'message'      => "Solicitud de reasignación enviada con éxito. Pendiente de aprobación por la cadena superior / Dirección.",
            'action_type'  => 'reassignment_requested',
            'solicitud_id' => $solicitud->id,
            'data'         => new TicketResource($ticket)
        ]);
    }

    /**
     * Aprueba una solicitud de reasignación pendiente (Superior jerárquico / Director).
     */
    public function approveReassignment(Request $request, $id, $requestId, TicketAssignmentService $assignmentService)
    {
        $user = $request->user();

        $ticket = Ticket::with(['categorias', 'prioridad', 'assignee.cargo'])->findOrFail($id);
        $reassignment = Asignacion::with(['participantes.usuario.rol', 'participantes.usuario.cargo'])
            ->where('ticket_codigo', $ticket->codigo)
            ->where('estado', Asignacion::ESTADO_PENDIENTE)
            ->findOrFail($requestId);

        // Aprobación jerárquica (D5): solo la cadena superior del técnico solicitante
        if ($denied = $this->denyIfCannotResolve($user, $reassignment, $ticket, 'aprobar')) {
            return $denied;
        }

        if ($this->isTicketClosedOrResolved($ticket)) {
            return response()->json([
                'message' => 'No se puede aprobar la reasignación porque el ticket se encuentra en estado Resuelto o Cerrado.',
                'error'   => 'ticket_closed_or_resolved',
            ], 422);
        }

        $newAgentCi = $reassignment->tecnicoDestinoCi();
        if (!$newAgentCi) {
            return response()->json([
                'message' => 'La solicitud de reasignación no tiene un técnico destino válido.',
                'error'   => 'missing_target_agent',
            ], 422);
        }

        $newAgent = User::with(['categorias', 'prioridades', 'rol', 'cargo'])->findOrFail($newAgentCi);
        $validation = $assignmentService->validateEligibility($newAgent, $ticket);
        if (!$validation['eligible']) {
            return response()->json([
                'message' => "El técnico propuesto ya no cumple los criterios de asignación: " . $validation['reason'],
                'error'   => 'ineligible_agent'
            ], 422);
        }

        // Si el ticket cambió de nivel (relevamiento) mientras la solicitud estaba pendiente, ya no aplica
        if ($levelError = app(\App\Services\TicketEscalationService::class)->reassignmentLevelError($ticket, $newAgent)) {
            return response()->json([
                'message' => $levelError,
                'error'   => 'reassign_other_level',
            ], 422);
        }

        $oldAssigneeName = $ticket->assignee ? $ticket->assignee->name : 'Sin asignar';
        $comments = trim($request->comments ?? $request->motivo ?? '');

        DB::transaction(function () use ($ticket, $reassignment, $newAgent, $user, $comments) {
            $now = now();

            $reassignment->update([
                'estado'               => Asignacion::ESTADO_APROBADA,
                'motivo_resolucion'    => $comments ?: 'Reasignación autorizada y aprobada por la Dirección.',
                'cargo_destino_codigo' => $newAgent->cargo_codigo,
                'fecha_resolucion'     => $now,
            ]);

            $rolAprobador = ($user->ci === $newAgent->ci)
                ? UsuarioAsignacion::ROL_DESTINO_APROBADOR
                : UsuarioAsignacion::ROL_APROBADOR;
            $reassignment->registrarParticipante($user->ci, $rolAprobador, $now);

            $ticket->tecnico_asignado_ci = $newAgent->ci;
            $ticket->save();
            app(\App\Services\TicketEscalationService::class)->recalcularNivelRelevamiento($ticket->fresh(), $newAgent->cargo);
        });

        TicketHistory::create([
            'ticket_codigo'   => $ticket->codigo,
            'usuario_ci'      => $user->ci,
            'estado_anterior' => $ticket->status,
            'estado_nuevo'    => $ticket->status,
            'comentarios'     => "Reasignación aprobada por {$user->name}" . ($user->cargo ? " ({$user->cargo->nombre})" : '') . ". Ticket reasignado de {$oldAssigneeName} a {$newAgent->name}." . ($comments ? " Nota: {$comments}" : ''),
        ]);

        try {
            app(\App\Services\NotificationService::class)->notifyReassignmentApproved($ticket, $reassignment->fresh(), $newAgent, $user);
        } catch (\Throwable $ne) {
            \Log::warning("Error al emitir notificación de aprobación de reasignación: " . $ne->getMessage());
        }

        $ticket->refresh()->load([
            'categorias', 'prioridad', 'assignee.rol', 'assignee.cargo', 'creator.rol', 'history.changer.rol',
            'solicitudReasignacionPendiente.participantes.usuario.rol',
            'solicitudReasignacionPendiente.participantes.usuario.cargo',
            'asignaciones.participantes.usuario.rol',
            'asignaciones.participantes.usuario.cargo',
            'asignaciones.cargoOrigen',
            'asignaciones.cargoDestino',
        ]);

        return response()->json([
            'message' => "Reasignación aprobada exitosamente. El ticket ahora está asignado a {$newAgent->name}.",
            'data'    => new TicketResource($ticket)
        ]);
    }

    /**
     * Rechaza una solicitud de reasignación pendiente (Superior jerárquico / Director).
     */
    public function rejectReassignment(Request $request, $id, $requestId)
    {
        $user = $request->user();

        $ticket = Ticket::with(['categorias', 'prioridad', 'assignee'])->findOrFail($id);
        $reassignment = Asignacion::with(['participantes.usuario.rol', 'participantes.usuario.cargo'])
            ->where('ticket_codigo', $ticket->codigo)
            ->where('estado', Asignacion::ESTADO_PENDIENTE)
            ->findOrFail($requestId);

        // Aprobación jerárquica (D5): solo la cadena superior del técnico solicitante
        if ($denied = $this->denyIfCannotResolve($user, $reassignment, $ticket, 'rechazar')) {
            return $denied;
        }

        $solicitante = $reassignment->solicitante() ?? $ticket->assignee;
        $motivoRechazo = trim($request->comments ?? $request->motivo ?? $request->reason ?? 'Solicitud denegada por la Dirección.');

        DB::transaction(function () use ($reassignment, $user, $motivoRechazo) {
            $now = now();

            $reassignment->update([
                'estado'            => Asignacion::ESTADO_RECHAZADA,
                'motivo_resolucion' => $motivoRechazo,
                'fecha_resolucion'  => $now,
            ]);

            $rolRechazador = ($user->ci === $reassignment->tecnicoDestinoCi())
                ? UsuarioAsignacion::ROL_DESTINO_RECHAZADOR
                : UsuarioAsignacion::ROL_RECHAZADOR;
            $reassignment->registrarParticipante($user->ci, $rolRechazador, $now);
        });

        TicketHistory::create([
            'ticket_codigo'   => $ticket->codigo,
            'usuario_ci'      => $user->ci,
            'estado_anterior' => $ticket->status,
            'estado_nuevo'    => $ticket->status,
            'comentarios'     => "Solicitud de reasignación rechazada por {$user->name}" . ($user->cargo ? " ({$user->cargo->nombre})" : '') . ". Motivo: {$motivoRechazo}",
        ]);

        try {
            if ($solicitante) {
                app(\App\Services\NotificationService::class)->notifyReassignmentRejected($ticket, $reassignment->fresh(), $solicitante, $user);
            }
        } catch (\Throwable $ne) {
            \Log::warning("Error al emitir notificación de rechazo de reasignación: " . $ne->getMessage());
        }

        $ticket->refresh()->load([
            'categorias', 'prioridad', 'assignee.rol', 'assignee.cargo', 'creator.rol', 'history.changer.rol',
            'solicitudReasignacionPendiente.participantes.usuario.rol',
            'solicitudReasignacionPendiente.participantes.usuario.cargo',
            'asignaciones.participantes.usuario.rol',
            'asignaciones.participantes.usuario.cargo',
            'asignaciones.cargoOrigen',
            'asignaciones.cargoDestino',
        ]);

        return response()->json([
            'message' => "Solicitud de reasignación rechazada.",
            'data'    => new TicketResource($ticket)
        ]);
    }

    /**
     * Lista todas las solicitudes de reasignación pendientes para el panel administrativo / superiores.
     */
    public function pendingReassignments(Request $request)
    {
        $user = $request->user();
        $approval = app(\App\Services\ReassignmentApprovalService::class);

        // Aprobación jerárquica (D5): solo las solicitudes que este usuario puede resolver
        $pending = Asignacion::with([
            'ticket.prioridad',
            'ticket.categorias',
            'ticket.assignee',
            'participantes.usuario.rol',
            'participantes.usuario.cargo',
        ])
        ->where('estado', Asignacion::ESTADO_PENDIENTE)
        ->orderBy('fecha_creacion', 'desc')
        ->get()
        ->filter(fn ($p) => $user && $p->ticket && $approval->canResolve($user, $p, $p->ticket))
        ->values();

        $formatted = $pending->map(function ($p) use ($approval) {
            $solicitante = $p->solicitante() ?? $p->ticket?->assignee;
            $propuesto = $p->tecnicoDestino();
            $anterior = $p->tecnicoOrigen() ?? $p->ticket?->assignee;

            return [
                'aprobadores'          => $approval->approversSummary($approval->approvers($p, $p->ticket)),
                'id'                   => $p->id,
                'ticket_id'            => $p->ticket_codigo,
                'ticket_codigo'        => $p->ticket_codigo,
                'motivo'               => $p->motivo_solicitud,
                'motivo_solicitud'     => $p->motivo_solicitud,
                'fecha_solicitud'      => $p->fecha_creacion?->toIso8601String(),
                'estado'               => $p->estado,
                'tipo_origen'          => $p->tipo_origen,
                'ticket'               => $p->ticket,
                'tecnico_propuesto'    => $propuesto,
                'tecnicoPropuesto'     => $propuesto,
                'tecnico_anterior'     => $anterior,
                'tecnicoAnterior'      => $anterior,
                'solicitante'          => $solicitante,
            ];
        });

        return response()->json([
            'total' => $pending->count(),
            'data'  => $formatted
        ]);
    }
}
