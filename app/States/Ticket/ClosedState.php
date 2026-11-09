<?php

namespace App\States\Ticket;

class ClosedState extends TicketState
{
    public const STATUS = self::STATUS_CLOSED;

    public function name(): string
    {
        return self::STATUS;
    }

    public function label(): string
    {
        return 'Cerrado';
    }

    public function allowedTransitions(): array
    {
        return [];
    }
}

