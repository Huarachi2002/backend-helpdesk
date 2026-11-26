<?php

namespace App\Services;

use App\Contracts\Mail\MailReceiver;
use App\Contracts\Mail\MailSender;
use App\Exceptions\InvalidStateTransitionException;
use App\Exceptions\TicketReplyException;
use App\Models\Ticket;
use App\Models\TicketMensaje;
use App\Models\User;
use App\Services\Mail\IncomingMail;
use App\Services\Mail\InboundMailResult;
use App\Services\Mail\OutgoingMail;
use App\Services\Mail\ReplyTextExtractor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Convierte un correo recibido en una respuesta del ticket.
 *
 * 1. Descarta respuestas automáticas, correos propios y suplantaciones (DMARC).
 * 2. Ubica el ticket por el hilo (In-Reply-To / References contra los Message-ID
 *    guardados) y, si no, por el código del asunto (ej. "TCK-008").
 * 3. Identifica al remitente por su correo registrado.
 * 4. Registra la respuesta con TicketReplyService (canal EMAIL), que aplica las
 *    mismas reglas que la web: cambio de estado, correo a la contraparte y
 *    notificación en el sistema.
 */
class InboundMailProcessor
{
    protected TicketReplyService $replies;
    protected ReplyTextExtractor $extractor;
    protected MailSender $sender;
    protected MailService $mailService;
    protected MailReceiver $receiver;

    public function __construct(
        TicketReplyService $replies,
        ReplyTextExtractor $extractor,
        MailSender $sender,
        MailService $mailService,
        MailReceiver $receiver
    ) {
        $this->replies = $replies;
        $this->extractor = $extractor;
        $this->sender = $sender;
        $this->mailService = $mailService;
        $this->receiver = $receiver;
    }

    public function process(IncomingMail $mail, bool $dryRun = false): InboundMailResult
    {
        $fromEmail = strtolower($mail->from->email ?? '');

        if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            return InboundMailResult::make(InboundMailResult::IGNORED, 'Correo sin remitente válido.');
        }

        if ($this->isOwnAddress($fromEmail)) {
            return InboundMailResult::make(InboundMailResult::IGNORED, 'Correo enviado por el propio sistema.');
        }

        if ($mail->isAutomatic()) {
            return InboundMailResult::make(InboundMailResult::IGNORED, 'Respuesta automática (fuera de oficina, rebote o lista).');
        }

        if ($mail->messageId && TicketMensaje::where('correo_message_id', $mail->messageId)->exists()) {
            return InboundMailResult::make(InboundMailResult::DUPLICATE, 'El correo ya estaba registrado.');
        }

        $ticket = $this->findTicket($mail);
        if (!$ticket) {
            return InboundMailResult::make(InboundMailResult::IGNORED, 'No corresponde a ningún ticket.');
        }

        if ($mail->failsDmarc()) {
            return InboundMailResult::make(InboundMailResult::REJECTED, "Posible suplantación de {$fromEmail} (DMARC fallido).", $ticket);
        }

        $author = User::with('rol')->whereRaw('LOWER(correo) = ?', [$fromEmail])->first();
        if (!$author) {
            return InboundMailResult::make(InboundMailResult::REJECTED, "El remitente {$fromEmail} no está registrado en el sistema.", $ticket);
        }
        if (isset($author->activo) && !$author->activo) {
            return InboundMailResult::make(InboundMailResult::REJECTED, "El usuario {$fromEmail} está inactivo.", $ticket);
        }

        $body = mb_substr($this->extractor->extract($mail->textBody, $mail->htmlBody), 0, 10000);
        if (mb_strlen($body) < 2) {
            return InboundMailResult::make(InboundMailResult::REJECTED, 'La respuesta no tiene contenido (solo texto citado).', $ticket);
        }

        try {
            // Valida permisos sin registrar nada (también en dry-run).
            $this->replies->resolveEmisor($ticket, $author);

            if ($dryRun) {
                return InboundMailResult::make(InboundMailResult::MATCHED, "Se registraría como respuesta de {$author->name}.", $ticket);
            }

            $result = $this->replies->reply($ticket, $author, $body, null, TicketReplyService::CANAL_EMAIL, $mail->messageId);
        } catch (TicketReplyException $e) {
            if ($e->getErrorCode() === 'ticket_not_replyable' && !$dryRun) {
                $this->notifyClosedTicket($mail, $ticket, $author);
            }

            return InboundMailResult::make(InboundMailResult::REJECTED, $e->getMessage(), $ticket);
        } catch (InvalidStateTransitionException $e) {
            return InboundMailResult::make(InboundMailResult::REJECTED, $e->getMessage(), $ticket);
        }

        return new InboundMailResult(
            InboundMailResult::REGISTERED,
            "Respuesta de {$author->name} registrada.",
            $ticket,
            $result['mensaje'],
            $result['old_status'],
            $result['new_status']
        );
    }

    /**
     * Busca el ticket por el hilo del correo y, si no, por el código del asunto.
     */
    public function findTicket(IncomingMail $mail): ?Ticket
    {
        $ids = $mail->threadIds();

        if ($ids) {
            $mensaje = TicketMensaje::whereIn('correo_message_id', $ids)->latest('fecha_creacion')->first();
            if ($mensaje) {
                return Ticket::find($mensaje->ticket_id);
            }

            $ticket = Ticket::whereIn('correo_hilo', $ids)->first();
            if ($ticket) {
                return $ticket;
            }
        }

        if (preg_match('/\b(TCK-\d+)\b/i', $mail->subject, $m)) {
            return Ticket::where('codigo', strtoupper($m[1]))->first();
        }

        return null;
    }

    protected function isOwnAddress(string $email): bool
    {
        $own = array_filter([
            $this->receiver->mailboxAddress(),
            $this->mailService->getSender()['address'] ?? null,
            config('mail.from.address'),
        ]);

        foreach ($own as $address) {
            if (strcasecmp($email, (string) $address) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Avisa (una vez por día y ticket) que el ticket ya no admite respuestas.
     */
    protected function notifyClosedTicket(IncomingMail $mail, Ticket $ticket, User $author): void
    {
        $key = 'helpdesk:inbound:closed-notice:' . $ticket->id . ':' . $author->id;
        if (!Cache::add($key, true, now()->addDay())) {
            return;
        }

        try {
            $sender = $this->mailService->getSender();
            $data = [
                'subject'       => "Re: [{$ticket->codigo}] {$ticket->asunto}",
                'recipientName' => $author->name,
                'ticket'        => $ticket,
                'statusLabel'   => $ticket->state()->label(),
                'actionUrl'     => $this->mailService->getFrontendUrl() . '/tickets',
            ];

            $result = $this->sender->send(
                OutgoingMail::make()
                    ->from($sender['address'], $sender['name'])
                    ->to($author->correo, $author->name)
                    ->subject($data['subject'])
                    ->html(view('emails.respuesta_rechazada', $data)->render())
                    ->inReplyTo($mail->messageId)
                    ->references(array_merge($mail->references, array_filter([$mail->messageId])))
            );

            if (!$result->success) {
                Log::warning("No se pudo avisar a {$author->correo} que {$ticket->codigo} no admite respuestas: {$result->error}");
            }
        } catch (\Throwable $e) {
            Log::warning('Error al enviar aviso de ticket cerrado: ' . $e->getMessage());
        }
    }
}
