<?php

namespace App\States\Ticket;

class InProgressState extends TicketState
{
    public const STATUS = self::STATUS_IN_PROGRESS;

    public function name(): string
    {
        return self::STATUS;
    }

    public function label(): string
    {
        return 'En Proceso';
    }

    public function allowedTransitions(): array
    {
        return [
            self::STATUS_WAITING,
            self::STATUS_RESOLVED,
            self::STATUS_CLOSED,
        ];
    }

    public function applyAttributes(): void
    {
        if (is_null($this->ticket->first_response_at)) {
            $this->ticket->first_response_at = now();
        }
    }
}

