<?php

namespace App\Exceptions;

use Exception;

/**
 * Error de negocio al registrar una respuesta en un ticket.
 */
class TicketReplyException extends Exception
{
    protected string $errorCode;
    protected int $httpStatus;

    public function __construct(string $message, string $errorCode, int $httpStatus = 422)
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
        $this->httpStatus = $httpStatus;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}
