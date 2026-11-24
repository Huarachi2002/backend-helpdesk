<?php

namespace App\Services\Mail;

/**
 * DTO independiente del proveedor que describe un correo saliente.
 *
 * Los encabezados de hilo (Message-ID, In-Reply-To, References) permiten que los
 * clientes de correo (Gmail, Outlook, Thunderbird) agrupen todas las respuestas
 * de un ticket en una sola conversación.
 */
final class OutgoingMail
{
    /** @var MailAddress[] */
    public array $to = [];

    /** @var MailAddress[] */
    public array $cc = [];

    public ?MailAddress $from = null;
    public ?MailAddress $replyTo = null;

    public string $subject = '';
    public string $htmlBody = '';
    public ?string $textBody = null;

    /** Message-ID propio, sin los caracteres < >. */
    public ?string $messageId = null;

    /** Message-ID del correo al que se responde, sin < >. */
    public ?string $inReplyTo = null;

    /** @var string[] Message-IDs previos del hilo, sin < >. */
    public array $references = [];

    /** @var array<int, array{path: string, name: ?string, mime: ?string}> */
    public array $attachments = [];

    public static function make(): self
    {
        return new self();
    }

    public function to(string $email, ?string $name = null): self
    {
        $this->to[] = new MailAddress($email, $name);
        return $this;
    }

    public function cc(string $email, ?string $name = null): self
    {
        $this->cc[] = new MailAddress($email, $name);
        return $this;
    }

    public function from(string $email, ?string $name = null): self
    {
        $this->from = new MailAddress($email, $name);
        return $this;
    }

    public function replyTo(string $email, ?string $name = null): self
    {
        $this->replyTo = new MailAddress($email, $name);
        return $this;
    }

    public function subject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function html(string $html): self
    {
        $this->htmlBody = $html;
        return $this;
    }

    public function text(?string $text): self
    {
        $this->textBody = $text;
        return $this;
    }

    public function messageId(?string $id): self
    {
        $this->messageId = $id ? self::stripBrackets($id) : null;
        return $this;
    }

    public function inReplyTo(?string $id): self
    {
        $this->inReplyTo = $id ? self::stripBrackets($id) : null;
        return $this;
    }

    /**
     * @param string[] $ids
     */
    public function references(array $ids): self
    {
        $this->references = array_values(array_unique(array_filter(array_map(
            fn ($id) => $id ? self::stripBrackets($id) : null,
            $ids
        ))));
        return $this;
    }

    public function attach(string $path, ?string $name = null, ?string $mime = null): self
    {
        $this->attachments[] = ['path' => $path, 'name' => $name, 'mime' => $mime];
        return $this;
    }

    /**
     * Devuelve solo los destinatarios con dirección válida.
     *
     * @return MailAddress[]
     */
    public function validRecipients(): array
    {
        return array_values(array_filter($this->to, fn (MailAddress $a) => $a->isValid()));
    }

    public static function stripBrackets(string $id): string
    {
        return trim($id, " \t\n\r\0\x0B<>");
    }
}
