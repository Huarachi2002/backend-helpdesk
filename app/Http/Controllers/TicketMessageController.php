<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidStateTransitionException;
use App\Exceptions\TicketReplyException;
use App\Http\Resources\TicketMessageResource;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\TicketReplyService;
use App\States\Ticket\TicketStateFactory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TicketMessageController extends Controller
{
    /**
     * El servicio (y su MailSender) se resuelven por inyección de dependencias.
     */
    protected TicketReplyService $replyService;

    public function __construct(TicketReplyService $replyService)
    {
        $this->replyService = $replyService;
    }

    /**
     * Conversación (respuestas) del ticket.
     */
    public function index($id)
    {
        $ticket = Ticket::findOrFail($id);
        $mensajes = $ticket->mensajes()->with('usuario.rol')->get();

        return TicketMessageResource::collection($mensajes);
    }

    /**
     * Registra una respuesta, cambia el estado del ticket y la envía por correo.
     */
    public function store(Request $request, $id)
    {
        $validated = $request->validate([
            'mensaje'     => 'required|string|min:2|max:10000',
            'next_status' => ['nullable', 'string', Rule::in(TicketStateFactory::availableStatuses())],
        ], [
            'mensaje.required' => 'Escribe el contenido de la respuesta.',
            'mensaje.min'      => 'La respuesta es demasiado corta.',
            'next_status.in'   => 'El estado destino indicado no es válido.',
        ]);

        $ticket = Ticket::findOrFail($id);

        try {
            $result = $this->replyService->reply(
                $ticket,
                $request->user(),
                trim($validated['mensaje']),
                $validated['next_status'] ?? null
            );
        } catch (TicketReplyException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error'   => $e->getErrorCode(),
            ], $e->getHttpStatus());
        } catch (InvalidStateTransitionException $e) {
            return response()->json([
                'message'             => $e->getMessage(),
                'error'               => 'invalid_state_transition',
                'current_status'      => $e->getCurrentState(),
                'target_status'       => $e->getTargetState(),
                'allowed_transitions' => $e->getAllowedTransitions(),
            ], 422);
        }

        $ticket->refresh()->load([
            'categorias', 'prioridad', 'assignee.rol', 'assignee.cargo', 'creator.rol', 'history.changer.rol',
            'solicitudReasignacionPendiente.participantes.usuario.rol',
            'solicitudReasignacionPendiente.participantes.usuario.cargo',
            'asignaciones.participantes.usuario.rol',
            'asignaciones.participantes.usuario.cargo',
            'asignaciones.cargoOrigen',
            'asignaciones.cargoDestino',
            'mensajes.usuario.rol',
        ]);

        $email = $result['email'];
        $message = $email && $email->success
            ? 'Respuesta registrada y enviada por correo.'
            : 'Respuesta registrada, pero no se pudo enviar el correo.';

        return response()->json([
            'message'       => $message,
            'status_change' => [
                'from'    => $result['old_status'],
                'to'      => $result['new_status'],
                'changed' => $result['old_status'] !== $result['new_status'],
            ],
            'email'         => $email ? $email->toArray() : null,
            'mensaje'       => new TicketMessageResource($result['mensaje']),
            'data'          => new TicketResource($ticket),
        ], 201);
    }
}
