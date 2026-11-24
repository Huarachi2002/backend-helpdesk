<?php

namespace App\Services\Mail\Senders;

use App\Contracts\Mail\MailSender;
use App\Services\Mail\MailAddress;
use App\Services\Mail\MailSendResult;
use App\Services\Mail\OutgoingMail;
use Illuminate\Support\Facades\Log;

/**
 * Implementación para desarrollo y pruebas: no envía nada, solo registra el
 * correo en storage/logs. Útil cuando no hay servidor SMTP disponible.
 */
class LogMailSender implements MailSender
{
    /** @var array<string, mixed> */
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function driverName(): string
    {
        return (string) ($this->config['name'] ?? 'log');
    }

    public function send(OutgoingMail $mail): MailSendResult
    {
        $recipients = $mail->validRecipients();
        if (empty($recipients)) {
            return MailSendResult::failed($this->driverName(), 'El correo no tiene destinatarios válidos.');
        }

        $from = $mail->from ?? new MailAddress((string) (config('mail.from.address') ?? 'no-reply@helpdesk.local'));
        $messageId = $mail->messageId ?: SmtpMailSender::generateMessageId($from);

        $logger = !empty($this->config['channel']) ? Log::channel($this->config['channel']) : Log::getFacadeRoot();
        $logger->info('[Mail:log] Correo simulado', [
            'message_id'  => $messageId,
            'in_reply_to' => $mail->inReplyTo,
            'references'  => $mail->references,
            'from'        => $from->email,
            'to'          => array_map(fn (MailAddress $a) => $a->email, $recipients),
            'subject'     => $mail->subject,
            'text'        => $mail->textBody,
            'html_length' => strlen($mail->htmlBody),
            'attachments' => array_column($mail->attachments, 'name'),
        ]);

        return MailSendResult::sent($this->driverName(), $messageId);
    }
}
