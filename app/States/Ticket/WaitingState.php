<?php

namespace App\States\Ticket;

class WaitingState extends TicketState
{
    public const STATUS = self::STATUS_WAITING;

    public function name(): string
    {
        return self::STATUS;
    }

    public function label(): string
    {
        return 'Esperando Respuesta';
    }

    public function allowedTransitions(): array
    {
        return [
            self::STATUS_IN_PROGRESS,
            self::STATUS_RESOLVED,
            self::STATUS_CLOSED,
        ];
    }
}

