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
            // Primera respuesta del soporte: el ticket pasa a esperar al cliente.
            self::STATUS_WAITING,
        ];
    }
}

