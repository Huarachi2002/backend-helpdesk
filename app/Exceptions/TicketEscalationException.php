<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Regla de negocio incumplida al relevar o devolver un ticket.
 * Se traduce a una respuesta HTTP 422 con un código de error legible.
 */
class TicketEscalationException extends RuntimeException
{
    protected string $errorCode;

    public function __construct(string $message, string $errorCode = 'escalation_not_allowed')
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
