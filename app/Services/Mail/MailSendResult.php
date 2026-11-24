<?php

namespace App\Services\Mail;

/**
 * Resultado del envío de un correo, independiente del proveedor.
 */
final class MailSendResult
{
    public bool $success;
    public string $driver;
    public ?string $messageId;
    public ?string $error;

    private function __construct(bool $success, string $driver, ?string $messageId, ?string $error)
    {
        $this->success   = $success;
        $this->driver    = $driver;
        $this->messageId = $messageId;
        $this->error     = $error;
    }

    public static function sent(string $driver, ?string $messageId): self
    {
        return new self(true, $driver, $messageId, null);
    }

    public static function failed(string $driver, string $error, ?string $messageId = null): self
    {
        return new self(false, $driver, $messageId, $error);
    }

    public function toArray(): array
    {
        return [
            'sent'       => $this->success,
            'driver'     => $this->driver,
            'message_id' => $this->messageId,
            'error'      => $this->error,
        ];
    }
}
