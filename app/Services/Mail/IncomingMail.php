<?php

namespace App\Services\Mail;

use DateTimeInterface;

/**
 * DTO de un correo recibido, independiente del servidor/librería que lo leyó.
 */
final class IncomingMail
{
    public string $uid;
    public ?string $messageId;
    public ?string $inReplyTo;
    /** @var string[] */
    public array $references;
    public ?MailAddress $from;
    public string $subject;
    public string $textBody;
    public string $htmlBody;
    public ?DateTimeInterface $date;
    public string $rawHeaders;

    public function __construct(
        string $uid,
        ?string $messageId,
        ?string $inReplyTo,
        array $references,
        ?MailAddress $from,
        string $subject,
        string $textBody,
        string $htmlBody,
        ?DateTimeInterface $date = null,
        string $rawHeaders = ''
    ) {
        $this->uid        = $uid;
        $this->messageId  = self::normalizeId($messageId);
        $this->inReplyTo  = self::normalizeId($inReplyTo);
        $this->references = array_values(array_filter(array_map([self::class, 'normalizeId'], $references)));
        $this->from       = $from;
        $this->subject    = trim($subject);
        $this->textBody   = $textBody;
        $this->htmlBody   = $htmlBody;
        $this->date       = $date;
        $this->rawHeaders = $rawHeaders;
    }

    /**
     * Message-IDs a los que responde este correo (In-Reply-To + References),
     * del más reciente al más antiguo.
     *
     * @return string[]
     */
    public function threadIds(): array
    {
        $ids = array_reverse($this->references);
        if ($this->inReplyTo) {
            array_unshift($ids, $this->inReplyTo);
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Clave estable para deduplicar aunque el correo no traiga Message-ID.
     */
    public function dedupeKey(): string
    {
        return $this->messageId
            ?: sha1($this->uid . '|' . ($this->from->email ?? '') . '|' . $this->subject . '|' . ($this->date ? $this->date->format(DATE_ATOM) : ''));
    }

    /**
     * Valor de un encabezado (sin distinguir mayúsculas), o null si no existe.
     */
    public function header(string $name): ?string
    {
        $unfolded = preg_replace("/\r?\n[ \t]+/", ' ', $this->rawHeaders);
        if (preg_match('/^' . preg_quote($name, '/') . ':\s*(.*)$/mi', (string) $unfolded, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    /**
     * Respuestas automáticas (fuera de oficina, rebotes, listas) que no deben
     * registrarse como respuesta del usuario ni generar más correos (evita bucles).
     */
    public function isAutomatic(): bool
    {
        $autoSubmitted = strtolower((string) $this->header('Auto-Submitted'));
        if ($autoSubmitted !== '' && $autoSubmitted !== 'no') {
            return true;
        }

        if ($this->header('X-Autoreply') !== null || $this->header('X-Autorespond') !== null) {
            return true;
        }

        $precedence = strtolower((string) $this->header('Precedence'));
        if (in_array($precedence, ['bulk', 'junk', 'list', 'auto_reply'], true)) {
            return true;
        }

        if (stripos((string) $this->header('Content-Type'), 'multipart/report') !== false) {
            return true;
        }

        $local = strtolower(explode('@', $this->from->email ?? '')[0] ?? '');
        if (in_array($local, ['mailer-daemon', 'postmaster', 'no-reply', 'noreply', 'do-not-reply'], true)) {
            return true;
        }

        return (bool) preg_match('/^(respuesta autom[aá]tica|automatic reply|auto(matic)?[ -]?reply|out of office|fuera de la oficina)\b/iu', $this->subject);
    }

    /**
     * true si el servidor receptor marcó el correo como suplantado (DMARC fallido).
     */
    public function failsDmarc(): bool
    {
        $auth = (string) $this->header('Authentication-Results');
        return $auth !== '' && (bool) preg_match('/\bdmarc=fail\b/i', $auth);
    }

    public static function normalizeId(?string $id): ?string
    {
        if ($id === null) {
            return null;
        }
        $id = trim($id, " \t\n\r\0\x0B<>");

        return $id === '' ? null : $id;
    }
}
