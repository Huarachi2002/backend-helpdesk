<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
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
        $query = Ticket::query()->with(['categorias', 'prioridad', 'assignee.rol', 'creator.rol']);

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
        $ticket = Ticket::with(['categorias', 'prioridad', 'assignee.rol', 'creator.rol', 'history.changer.rol'])->findOrFail($id);
        return new TicketResource($ticket);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status'   => 'required|string',
            'comments' => 'nullable|string',
        ]);

        $ticket = Ticket::findOrFail($id);

        try {
            $ticket->transitionTo($request->status, $request->user(), $request->comments);
            $ticket->load(['categorias', 'prioridad', 'assignee', 'creator', 'history.changer']);

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
     * Reasigna el ticket a un nuevo técnico especializado.
     */
    public function reassign(Request $request, $id, TicketAssignmentService $assignmentService)
    {
        $request->validate([
            'assigned_to' => 'required|exists:usuarios,id',
            'comments'    => 'nullable|string',
        ], [
            'assigned_to.required' => 'Debes seleccionar un agente de soporte para la reasignación.',
            'assigned_to.exists'   => 'El usuario seleccionado no existe en el sistema.',
        ]);

        $ticket = Ticket::with(['categorias', 'prioridad'])->findOrFail($id);
        $newAgent = User::with(['categorias', 'prioridades', 'rol'])->findOrFail($request->assigned_to);

        // Validar elegibilidad
        $validation = $assignmentService->validateEligibility($newAgent, $ticket);
        if (!$validation['eligible']) {
            return response()->json([
                'message' => $validation['reason'],
                'error'   => 'ineligible_agent',
            ], 422);
        }

        $oldAssignee = $ticket->assignee;
        $oldAssigneeName = $oldAssignee ? $oldAssignee->name : 'Sin asignar';

        $ticket->tecnico_asignado_id = $newAgent->id;
        $ticket->save();

        $auditComment = $request->comments
            ? $request->comments
            : "Ticket reasignado de {$oldAssigneeName} a {$newAgent->name} ({$newAgent->email}).";

        TicketHistory::create([
            'ticket_id'  => $ticket->id,
            'changed_by' => $request->user()->id,
            'old_status' => $ticket->status,
            'new_status' => $ticket->status,
            'comments'   => $auditComment,
        ]);

        $ticket->load(['categorias', 'prioridad', 'assignee', 'creator', 'history.changer']);

        return response()->json([
            'message' => "Ticket reasignado exitosamente a {$newAgent->name}.",
            'data'    => new TicketResource($ticket)
        ]);
    }
}
