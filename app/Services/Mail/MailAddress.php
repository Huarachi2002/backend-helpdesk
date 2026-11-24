<?php

namespace App\Services\Mail;

/**
 * Value Object: dirección de correo con nombre opcional.
 */
final class MailAddress
{
    public string $email;
    public ?string $name;

    public function __construct(string $email, ?string $name = null)
    {
        $this->email = trim($email);
        $this->name  = $name !== null && trim($name) !== '' ? trim($name) : null;
    }

    public function isValid(): bool
    {
        return filter_var($this->email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Dominio de la dirección (se usa para generar Message-ID propios).
     */
    public function domain(): string
    {
        $parts = explode('@', $this->email);
        return count($parts) === 2 && $parts[1] !== '' ? $parts[1] : 'helpdesk.local';
    }
}
