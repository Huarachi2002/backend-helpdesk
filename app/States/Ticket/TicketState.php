<?php

namespace App\States\Ticket;

use App\Exceptions\InvalidStateTransitionException;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

abstract class TicketState
{
    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_WAITING = 'waiting';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';

    protected Ticket $ticket;

    public function __construct(Ticket $ticket)
    {
        $this->ticket = $ticket;
    }

    /**
     * Identificador interno del estado (e.g. 'open', 'in_progress').
     */
    abstract public function name(): string;

    /**
     * Nombre legible del estado (e.g. 'Abierto', 'En Proceso').
     */
    abstract public function label(): string;

    /**
     * Lista de estados a los que se puede transicionar desde este estado.
     *
     * @return string[]
     */
    abstract public function allowedTransitions(): array;

    /**
     * Determina si se puede cambiar al estado objetivo.
     */
    public function canTransitionTo(string $targetStatus): bool
    {
        return in_array($targetStatus, $this->allowedTransitions(), true);
    }

    /**
     * Ejecuta la transición de estado encapsulada en una transacción de BD.
     *
     * @throws InvalidStateTransitionException
     */
    public function transitionTo(string $targetStatus, ?User $user = null, ?string $comments = null): Ticket
    {
        if (!$this->canTransitionTo($targetStatus)) {
            throw new InvalidStateTransitionException(
                $this->name(),
                $targetStatus,
                $this->allowedTransitions()
            );
        }

        $targetState = TicketStateFactory::makeByKey($targetStatus, $this->ticket);

        return DB::transaction(function () use ($targetState, $user, $comments) {
            $oldStatus = $this->name();
            $newStatus = $targetState->name();

            // Actualizar estado del ticket
            $this->ticket->status = $newStatus;

            // Aplicar efectos secundarios propios del nuevo estado (fechas, etc.)
            $targetState->applyAttributes();

            $this->ticket->save();

            // Registrar en el historial de auditoría
            $userId = $user?->id ?? auth()->id() ?? $this->ticket->created_by;

            TicketHistory::create([
                'ticket_id'  => $this->ticket->id,
                'changed_by' => $userId,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'comments'   => $comments ?? "Transición de '{$this->label()}' a '{$targetState->label()}'.",
            ]);

            return $this->ticket;
        });
    }

    /**
     * Hook para aplicar modificaciones al ticket cuando entra a este estado.
     */
    public function applyAttributes(): void
    {
        // Por defecto no modifica atributos adicionales
    }

    /**
     * Atajo para transicionar a En Proceso.
     */
    public function toInProgress(?User $user = null, ?string $comments = null): Ticket
    {
        return $this->transitionTo(self::STATUS_IN_PROGRESS, $user, $comments);
    }

    /**
     * Atajo para transicionar a Esperando Respuesta.
     */
    public function toWaiting(?User $user = null, ?string $comments = null): Ticket
    {
        return $this->transitionTo(self::STATUS_WAITING, $user, $comments);
    }

    /**
     * Atajo para transicionar a Resuelto.
     */
    public function toResolved(?User $user = null, ?string $comments = null): Ticket
    {
        return $this->transitionTo(self::STATUS_RESOLVED, $user, $comments);
    }

    /**
     * Atajo para transicionar a Cerrado.
     */
    public function toClosed(?User $user = null, ?string $comments = null): Ticket
    {
        return $this->transitionTo(self::STATUS_CLOSED, $user, $comments);
    }
}

