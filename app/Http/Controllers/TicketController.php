<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketHistory;
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
    
        $validated['created_by'] = $request->user()->id;
        $validated['status'] = 'open';

        DB::beginTransaction();
        try {
            $ticket = Ticket::create($validated);

            $agent = $assignmentService->assign($ticket);

            $comments = $agent
                ? "Ticket asginado automaticamente a {$agent->email}."
                : "Ticket derivado a la cola general (sin agentes disponibles).";

            TicketHistory::create([
                'ticket_id' => $ticket->id,
                'changed_by' => $request->user()->id,
                'old_status' => null,
                'new_status' => 'open',
                'comments' => $comments
            ]);

            DB::commit();

            $ticket->load('assignee', 'category', 'creator');

            return response()->json([
                'message' => 'Ticket creado con exito',
                'data' => new TicketResource($ticket)
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear el ticket.',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function index(Request $request)
    {
        $query = Ticket::query()->with(['category', 'assignee', 'creator']);

        $query->when($request->status, fn($q, $status) => $q->where('status', $status))
              ->when($request->priority, fn($q, $priority) => $q->where('priority', $priority))
              ->when($request->category_id, fn($q, $category_id) => $q->where('category_id', $category_id))
              ->when($request->assigned_to, fn($q, $assigned_to) => $q->where('assigned_to', $assigned_to))
              ->when($request->date_from, fn($q, $date_from) => $q->whereDate('created_at', '>=', $date_from))
              ->when($request->date_to, fn($q, $date_to) => $q->whereDate('created_at', '<=', $date_to))
              ->when($request->search, fn($q, $search) => $q->where('title', 'like', "%{$search}%"));

        $sortField = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortField, $sortOrder);

        return TicketResource::collection($query->paginate(15));
    }

    public function show($id)
    {
        $ticket = Ticket::with(['category', 'assignee', 'creator', 'history.changer'])->findOrFail($id);
        return new TicketResource($ticket);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status'   => 'required|in:open,in_progress,waiting,resolved,closed',
            'comments' => 'nullable|string',
        ]);

        $ticket = Ticket::findOrFail($id);
        $oldStatus = $ticket->status;

        $ticket->status = $request->status;
        if ($request->status === 'resolved' && is_null($ticket->resolved_at)) {
            $ticket->resolved_at = now();
        }
        $ticket->save();

        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'changed_by' => $request->user()->id,
            'old_status' => $oldStatus,
            'new_status' => $ticket->status,
            'comments' => $request->comments ?? 'Cambio de estado manual.'
        ]);

        return response()->json(['message' => 'Estado actualizado', 'data' => new TicketResource($ticket)]);
    }

    public function reassign(Request $request, $id)
    {
        $request->validate([
            'assigned_to' => 'required|exists:users,id',
            'comments'    => 'nullable|string',
        ]);

        $ticket = Ticket::findOrFail($id);
        $oldAssignee = $ticket->assigned_to;
        $ticket->assigned_to = $request->assigned_to;
        $ticket->save();

        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'changed_by' => $request->user()->id,
            'old_status' => $ticket->status,
            'new_status' => $ticket->status,
            'comments' => $request->comments ?? "Ticket reasignado del agente ID {$oldAssignee} al ID {$request->assigned_to}."
        ]);

        return response()->json(['message' => 'Ticket Reasignado', 'data' => new TicketResource($ticket)]);
    }

}
