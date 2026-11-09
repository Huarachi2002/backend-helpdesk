<?php

namespace App\States\Ticket;

use App\Models\Ticket;
use InvalidArgumentException;

class TicketStateFactory
{
    /**
     * Mapeo de identificadores de estado a sus respectivas clases.
     */
    protected static array $stateMap = [
        OpenState::STATUS        => OpenState::class,
        InProgressState::STATUS  => InProgressState::class,
        WaitingState::STATUS     => WaitingState::class,
        ResolvedState::STATUS    => ResolvedState::class,
        ClosedState::STATUS      => ClosedState::class,
    ];

    /**
     * Instancia la clase de estado correspondiente al estado actual del ticket.
     */
    public static function make(Ticket $ticket): TicketState
    {
        $status = $ticket->status ?? OpenState::STATUS;
        return self::makeByKey($status, $ticket);
    }

    /**
     * Instancia la clase de estado correspondiente a una clave dada.
     *
     * @throws InvalidArgumentException
     */
    public static function makeByKey(string $status, Ticket $ticket): TicketState
    {
        if (!isset(self::$stateMap[$status])) {
            $valid = implode(', ', array_keys(self::$stateMap));
            throw new InvalidArgumentException("El estado '{$status}' no es válido. Estados disponibles: [{$valid}].");
        }

        $stateClass = self::$stateMap[$status];
        return new $stateClass($ticket);
    }

    /**
     * Retorna todas las claves de estado disponibles.
     */
    public static function availableStatuses(): array
    {
        return array_keys(self::$stateMap);
    }

    /**
     * Retorna el mapeo completo de estados.
     */
    public static function stateMap(): array
    {
        return self::$stateMap;
    }
}

