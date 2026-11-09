<?php

namespace App\States\Ticket;

class ResolvedState extends TicketState
{
    public const STATUS = self::STATUS_RESOLVED;

    public function name(): string
    {
        return self::STATUS;
    }

    public function label(): string
    {
        return 'Resuelto';
    }

    public function allowedTransitions(): array
    {
        return [];
    }

    public function applyAttributes(): void
    {
        if (is_null($this->ticket->resolved_at)) {
            $this->ticket->resolved_at = now();
        }
    }
}

