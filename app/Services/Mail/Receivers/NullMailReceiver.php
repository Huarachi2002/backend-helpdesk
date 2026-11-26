<?php

namespace App\Services\Mail\Receivers;

use App\Contracts\Mail\MailReceiver;
use App\Services\Mail\IncomingMail;

/**
 * Recepción de correo desactivada: no lee ningún buzón.
 */
class NullMailReceiver implements MailReceiver
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function fetch(int $limit = 50): array
    {
        return [];
    }

    public function markProcessed(IncomingMail $mail): void
    {
    }

    public function mailboxAddress(): ?string
    {
        return null;
    }

    public function driverName(): string
    {
        return (string) ($this->config['name'] ?? 'none');
    }
}
