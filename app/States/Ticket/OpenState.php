<?php

namespace App\States\Ticket;

class OpenState extends TicketState
{
    public const STATUS = self::STATUS_OPEN;

    public function name(): string
    {
        return self::STATUS;
    }

    public function label(): string
    {
        return 'Abierto';
    }

    public function allowedTransitions(): array
    {
        return [
            self::STATUS_IN_PROGRESS,
        ];
    }
}

