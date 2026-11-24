<?php

namespace App\Services\Mail\Senders;

use App\Contracts\Mail\MailSender;
use App\Services\Mail\MailAddress;
use App\Services\Mail\MailSendResult;
use App\Services\Mail\OutgoingMail;
use Illuminate\Mail\MailManager;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Implementación genérica SMTP: sirve para cualquier servidor de correo
 * (servidor propio del cliente, Office 365, Zoho, Mailtrap, MailHog, etc.).
 *
 * Recibe su configuración por constructor (inyección de dependencias), por lo
 * que no depende del mailer global de Laravel (config/mail.php → 'default').
 */
class SmtpMailSender implements MailSender
{
    protected MailManager $manager;

    /** @var array<string, mixed> */
    protected array $config;

    public function __construct(MailManager $manager, array $config = [])
    {
        $this->manager = $manager;

        // Los valores vacíos de .env no deben pisar los valores por defecto del driver.
        $provided = array_filter($config, fn ($v) => $v !== null && $v !== '' && $v !== 'null');
        $this->config = array_merge($this->defaults(), $provided);
    }

    /**
     * Valores por defecto del servidor. Las subclases (ej. Gmail) los sobrescriben.
     */
    protected function defaults(): array
    {
        return [
            'name'       => 'smtp',
            'port'       => 587,
            'encryption' => 'tls',
            'timeout'    => 30,
        ];
    }

    public function driverName(): string
    {
        return (string) ($this->config['name'] ?? 'smtp');
    }

    public function send(OutgoingMail $mail): MailSendResult
    {
        $recipients = $mail->validRecipients();
        if (empty($recipients)) {
            return MailSendResult::failed($this->driverName(), 'El correo no tiene destinatarios válidos.');
        }

        if (empty($this->config['host'])) {
            return MailSendResult::failed($this->driverName(), 'El servidor de correo no está configurado (host vacío).');
        }

        $from = $this->resolveFrom($mail);
        $replyTo = $this->resolveReplyTo($mail, $from);
        $messageId = $mail->messageId ?: self::generateMessageId($from);

        try {
            $this->mailer()->html($mail->htmlBody, function (Message $message) use ($mail, $recipients, $from, $replyTo, $messageId) {
                $message->from($from->email, $from->name);

                foreach ($recipients as $to) {
                    $message->to($to->email, $to->name);
                }
                foreach ($mail->cc as $cc) {
                    if ($cc->isValid()) {
                        $message->cc($cc->email, $cc->name);
                    }
                }
                if ($replyTo) {
                    $message->replyTo($replyTo->email, $replyTo->name);
                }

                $message->subject($mail->subject);

                foreach ($mail->attachments as $file) {
                    if (is_file($file['path'])) {
                        $message->attach($file['path'], array_filter([
                            'as'   => $file['name'] ?? null,
                            'mime' => $file['mime'] ?? null,
                        ]));
                    }
                }

                $this->applyLowLevelHeaders($message, $mail, $messageId);
            });

            return MailSendResult::sent($this->driverName(), $messageId);
        } catch (\Throwable $e) {
            $emails = implode(', ', array_map(fn (MailAddress $a) => $a->email, $recipients));
            Log::warning("[Mail:{$this->driverName()}] No se pudo enviar '{$mail->subject}' a {$emails}: " . $e->getMessage());

            return MailSendResult::failed($this->driverName(), $e->getMessage(), $messageId);
        }
    }

    /**
     * Construye (o reutiliza) un mailer de Laravel con la configuración propia de este driver.
     */
    protected function mailer()
    {
        $name = 'helpdesk_' . $this->driverName();
        $encryption = strtolower((string) ($this->config['encryption'] ?? ''));

        config(["mail.mailers.{$name}" => [
            'transport'    => 'smtp',
            'host'         => $this->config['host'],
            'port'         => (int) $this->config['port'],
            'encryption'   => in_array($encryption, ['', 'none', 'null'], true) ? null : $encryption,
            'username'     => $this->config['username'] ?? null,
            'password'     => $this->config['password'] ?? null,
            'timeout'      => $this->config['timeout'] ?? null,
            'auth_mode'    => null,
            'local_domain' => $this->config['local_domain'] ?? null,
        ]]);

        return $this->manager->mailer($name);
    }

    protected function resolveFrom(OutgoingMail $mail): MailAddress
    {
        if ($mail->from && $mail->from->isValid()) {
            return $mail->from;
        }

        return new MailAddress(
            (string) ($this->config['from_address'] ?? config('mail.from.address') ?? 'no-reply@helpdesk.local'),
            $this->config['from_name'] ?? config('mail.from.name')
        );
    }

    protected function resolveReplyTo(OutgoingMail $mail, MailAddress $from): ?MailAddress
    {
        return $mail->replyTo && $mail->replyTo->isValid() ? $mail->replyTo : null;
    }

    /**
     * Encabezados de hilo y parte de texto plano. Laravel 8 usa SwiftMailer y
     * Laravel 9+ usa Symfony Mailer; se soportan ambos para facilitar la migración.
     */
    protected function applyLowLevelHeaders(Message $message, OutgoingMail $mail, string $messageId): void
    {
        if (method_exists($message, 'getSwiftMessage')) {
            $swift = $message->getSwiftMessage();
            $swift->setId($messageId);
            $headers = $swift->getHeaders();

            if ($mail->inReplyTo) {
                $headers->addIdHeader('In-Reply-To', $mail->inReplyTo);
            }
            if (!empty($mail->references)) {
                $headers->addIdHeader('References', $mail->references);
            }
            if ($mail->textBody) {
                $swift->addPart($mail->textBody, 'text/plain');
            }
            return;
        }

        if (method_exists($message, 'getSymfonyMessage')) {
            $symfony = $message->getSymfonyMessage();
            $headers = $symfony->getHeaders();

            if ($headers->has('Message-ID')) {
                $headers->remove('Message-ID');
            }
            $headers->addIdHeader('Message-ID', $messageId);

            if ($mail->inReplyTo) {
                $headers->addIdHeader('In-Reply-To', $mail->inReplyTo);
            }
            if (!empty($mail->references)) {
                $headers->addIdHeader('References', $mail->references);
            }
            if ($mail->textBody) {
                $symfony->text($mail->textBody);
            }
        }
    }

    /**
     * Genera un Message-ID único (sin < >) usando el dominio del remitente.
     */
    public static function generateMessageId(MailAddress $from, string $prefix = 'helpdesk'): string
    {
        return sprintf('%s.%s.%s@%s', $prefix, now()->format('YmdHis'), Str::lower(Str::random(12)), $from->domain());
    }
}
