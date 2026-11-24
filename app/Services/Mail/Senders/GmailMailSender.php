<?php

namespace App\Services\Mail\Senders;

use App\Services\Mail\MailAddress;
use App\Services\Mail\OutgoingMail;

/**
 * Envío mediante el servidor SMTP de Google (Gmail / Google Workspace).
 *
 * Requiere una "Contraseña de aplicación" de Google (no la contraseña normal
 * de la cuenta) con la verificación en dos pasos activa.
 */
class GmailMailSender extends SmtpMailSender
{
    protected function defaults(): array
    {
        return [
            'name'       => 'gmail',
            'host'       => 'smtp.gmail.com',
            'port'       => 587,
            'encryption' => 'tls',
            'timeout'    => 30,
            // Gmail reescribe el remitente si no coincide con la cuenta autenticada.
            'force_account_as_from' => true,
        ];
    }

    /**
     * Gmail solo permite enviar como la cuenta autenticada (o un alias verificado).
     * Si el remitente configurado es otro, se usa la cuenta como "From" y la
     * dirección original pasa a "Reply-To" para que las respuestas lleguen bien.
     */
    protected function resolveFrom(OutgoingMail $mail): MailAddress
    {
        $from = parent::resolveFrom($mail);
        $account = (string) ($this->config['username'] ?? '');

        if ($this->shouldForceAccount($from, $account)) {
            return new MailAddress($account, $from->name);
        }

        return $from;
    }

    protected function resolveReplyTo(OutgoingMail $mail, MailAddress $from): ?MailAddress
    {
        $replyTo = parent::resolveReplyTo($mail, $from);
        if ($replyTo) {
            return $replyTo;
        }

        $original = parent::resolveFrom($mail);
        return strcasecmp($original->email, $from->email) !== 0 ? $original : null;
    }

    private function shouldForceAccount(MailAddress $from, string $account): bool
    {
        return !empty($this->config['force_account_as_from'])
            && filter_var($account, FILTER_VALIDATE_EMAIL)
            && strcasecmp($from->email, $account) !== 0;
    }
}
