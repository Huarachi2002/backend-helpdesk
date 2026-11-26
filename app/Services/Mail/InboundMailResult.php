<?php

namespace App\Services\Mail;

use App\Models\Ticket;
use App\Models\TicketMensaje;

/**
 * Resultado de procesar un correo entrante.
 */
final class InboundMailResult
{
    /** Registrado como respuesta del ticket. */
    public const REGISTERED = 'registered';
    /** Ya estaba registrado (mismo Message-ID). */
    public const DUPLICATE = 'duplicate';
    /** Coincide con un ticket y se registraría (modo --dry-run). */
    public const MATCHED = 'matched';
    /** No corresponde a ningún ticket o es automático: no se toca. */
    public const IGNORED = 'ignored';
    /** Corresponde a un ticket pero no se puede registrar (remitente no autorizado, ticket cerrado...). */
    public const REJECTED = 'rejected';

    public string $status;
    public string $reason;
    public ?Ticket $ticket;
    public ?TicketMensaje $mensaje;
    public ?string $oldStatus;
    public ?string $newStatus;

    public function __construct(string $status, string $reason, ?Ticket $ticket = null, ?TicketMensaje $mensaje = null, ?string $oldStatus = null, ?string $newStatus = null)
    {
        $this->status = $status;
        $this->reason = $reason;
        $this->ticket = $ticket;
        $this->mensaje = $mensaje;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
    }

    public static function make(string $status, string $reason, ?Ticket $ticket = null): self
    {
        return new self($status, $reason, $ticket);
    }

    /**
     * Si el correo ya quedó registrado y puede marcarse como leído en el buzón.
     */
    public function isRegistered(): bool
    {
        return in_array($this->status, [self::REGISTERED, self::DUPLICATE], true);
    }
}
