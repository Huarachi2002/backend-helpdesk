<?php

namespace App\Http\Controllers;

use App\Exceptions\TicketEscalationException;
use App\Http\Resources\TicketResource;
use App\Models\Asignacion;
use App\Models\Ticket;
use App\Services\TicketEscalationService;
use Illuminate\Http\Request;

/**
 * Relevamiento (movimiento vertical) y devolución de tickets en la Estructura Organizacional.
 */
class TicketEscalationController extends Controller
{
    protected TicketEscalationService $service;

    public function __construct(TicketEscalationService $service)
    {
        $this->service = $service;
    }

    /**
     * Niveles y usuarios disponibles para relevar el ticket, más la información de devolución.
     */
    public function options(Request $request, $id)
    {
        $ticket = Ticket::with(['assignee.cargo', 'solicitudReasignacionPendiente'])->findOrFail($id);
        $user = $request->user();

        return response()->json([
            'data' => array_merge(
                $this->service->getOptions($ticket, $user),
                ['devolucion' => $this->service->getReturnInfo($ticket, $user)]
            ),
        ]);
    }

    /**
     * Relevar el ticket a otro nivel (ascendente o descendente).
     */
    public function escalate(Request $request, $id)
    {
        $validated = $request->validate([
            'destino_usuario_id' => 'required|string|exists:usuarios,ci',
            'motivo'             => 'required|string|min:10|max:2000',
        ], [
            'destino_usuario_id.required' => 'Debes seleccionar al usuario que recibirá el ticket.',
            'destino_usuario_id.exists'   => 'El usuario seleccionado no existe.',
            'motivo.required'             => 'El motivo del relevamiento es obligatorio.',
            'motivo.min'                  => 'Describe el motivo del relevamiento con al menos 10 caracteres.',
        ]);

        $ticket = Ticket::with(['assignee.cargo', 'solicitudReasignacionPendiente'])->findOrFail($id);

        try {
            $movimiento = $this->service->escalate(
                $ticket,
                $request->user(),
                (string) $validated['destino_usuario_id'],
                trim($validated['motivo'])
            );
        } catch (TicketEscalationException $e) {
            return response()->json(['message' => $e->getMessage(), 'error' => $e->getErrorCode()], 422);
        }

        $movimiento->load(['participantes.usuario', 'cargoDestino']);
        $tecnicoDestino = $movimiento->tecnicoDestino();
        $direccion = $movimiento->tipo_origen === Asignacion::TIPO_RELEVAMIENTO_ASCENDENTE ? 'superior' : 'inferior';

        return response()->json([
            'message'     => "Ticket relevado al nivel {$direccion}: {$tecnicoDestino?->name} ({$movimiento->cargoDestino?->nombre}).",
            'action_type' => $movimiento->tipo_origen,
            'data'        => $this->ticketResource($ticket->codigo),
        ]);
    }

    /**
     * Devolver el ticket al nivel anterior.
     */
    public function returnTicket(Request $request, $id)
    {
        $validated = $request->validate([
            'instrucciones'      => 'required|string|min:10|max:2000',
            'destino_usuario_id' => 'nullable|string|exists:usuarios,ci',
        ], [
            'instrucciones.required' => 'Las instrucciones para la devolución son obligatorias.',
            'instrucciones.min'      => 'Describe las instrucciones con al menos 10 caracteres.',
        ]);

        $ticket = Ticket::with(['assignee.cargo', 'solicitudReasignacionPendiente'])->findOrFail($id);

        try {
            $movimiento = $this->service->returnToPrevious(
                $ticket,
                $request->user(),
                trim($validated['instrucciones']),
                isset($validated['destino_usuario_id']) ? (string) $validated['destino_usuario_id'] : null
            );
        } catch (TicketEscalationException $e) {
            return response()->json(['message' => $e->getMessage(), 'error' => $e->getErrorCode()], 422);
        }

        $movimiento->load(['participantes.usuario', 'cargoDestino']);
        $tecnicoDestino = $movimiento->tecnicoDestino();

        return response()->json([
            'message'     => "Ticket devuelto a {$tecnicoDestino?->name} ({$movimiento->cargoDestino?->nombre}).",
            'action_type' => $movimiento->tipo_origen,
            'data'        => $this->ticketResource($ticket->codigo),
        ]);
    }

    private function ticketResource(string $ticketCodigo): TicketResource
    {
        return new TicketResource(Ticket::with([
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
        ])->findOrFail($ticketCodigo));
    }
}
