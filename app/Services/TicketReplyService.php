<?php

namespace App\Services;

use App\Contracts\Mail\MailReceiver;
use App\Contracts\Mail\MailSender;
use App\Exceptions\TicketReplyException;
use App\Models\Notificacion;
use App\Models\Ticket;
use App\Models\TicketMensaje;
use App\Models\User;
use App\Services\Mail\MailSendResult;
use App\Services\Mail\OutgoingMail;
use App\States\Ticket\TicketState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Registra respuestas en un ticket, aplica el cambio de estado correspondiente
 * y envía la respuesta por correo a la contraparte.
 *
 * Reglas de estado automáticas:
 *  - Responde SOPORTE  : Abierto / En Proceso      → Esperando Respuesta (ESPERANDO_POR_CLIENTE)
 *  - Responde CLIENTE  : Esperando Respuesta       → En Proceso (CLIENTE_ESPERANDO_RESPUESTA)
 *  - Resuelto / Cerrado: no admiten respuestas.
 * El soporte puede indicar un estado destino explícito (ej. "responder y resolver").
 */
class TicketReplyService
{
    public const EMISOR_SOPORTE = 'SOPORTE';
    public const EMISOR_CLIENTE = 'CLIENTE';

    public const CANAL_WEB   = 'WEB';
    public const CANAL_EMAIL = 'EMAIL';

    protected MailSender $mailSender;
    protected MailService $mailService;
    protected NotificationService $notifications;
    protected MailReceiver $mailReceiver;

    public function __construct(
        MailSender $mailSender,
        MailService $mailService,
        NotificationService $notifications,
        MailReceiver $mailReceiver
    ) {
        $this->mailSender = $mailSender;
        $this->mailService = $mailService;
        $this->notifications = $notifications;
        $this->mailReceiver = $mailReceiver;
    }

    /**
     * @return array{mensaje: TicketMensaje, email: ?MailSendResult, old_status: string, new_status: string}
     *
     * @throws TicketReplyException
     * @throws \App\Exceptions\InvalidStateTransitionException
     */
    public function reply(
        Ticket $ticket,
        User $author,
        string $body,
        ?string $requestedStatus = null,
        string $canal = self::CANAL_WEB,
        ?string $incomingMessageId = null
    ): array {
        $ticket->loadMissing(['creator', 'assignee']);
        $author->loadMissing('rol');

        $oldStatus = $ticket->status;
        $this->ensureTicketAcceptsReplies($oldStatus);

        $emisor = $this->resolveEmisor($ticket, $author);

        if ($requestedStatus && $emisor !== self::EMISOR_SOPORTE) {
            throw new TicketReplyException('Solo el personal de soporte puede cambiar el estado al responder.', 'status_change_forbidden', 403);
        }

        $targetStatus = $requestedStatus ?: $this->automaticTargetStatus($oldStatus, $emisor);
        if ($targetStatus === $oldStatus) {
            $targetStatus = null;
        }

        $mensaje = DB::transaction(function () use ($ticket, $author, $body, $emisor, $targetStatus, $canal, $incomingMessageId) {
            $mensaje = TicketMensaje::create([
                'ticket_codigo'     => $ticket->codigo,
                'usuario_ci'        => $author->ci,
                'tipo_emisor'       => $emisor,
                'canal_origen'      => $canal,
                'remitente_correo'  => $author->correo ?? '',
                'remitente_nombre'  => $author->name,
                'cuerpo_mensaje'    => $body,
                // Respuesta recibida por correo: se guarda su Message-ID (hilo + deduplicación).
                'correo_mensaje_id' => $canal === self::CANAL_EMAIL ? $incomingMessageId : null,
            ]);

            if ($targetStatus) {
                // Lanza InvalidStateTransitionException si no es válida → rollback del mensaje.
                $ticket->transitionTo($targetStatus, $author, $this->historyComment($author, $emisor, $targetStatus, $canal));
            } else {
                // Sin cambio de estado: igual se marca la actividad del ticket.
                $ticket->touch();
            }

            if ($emisor === self::EMISOR_SOPORTE && is_null($ticket->fecha_respuesta)) {
                $ticket->fecha_respuesta = now();
                $ticket->save();
            }

            return $mensaje;
        });

        $ticket->refresh()->loadMissing(['creator', 'assignee']);

        $recipient = $emisor === self::EMISOR_SOPORTE ? $ticket->creator : $ticket->assignee;
        $emailResult = $this->sendReplyEmail($ticket, $mensaje, $author, $recipient);
        $this->notifyInApp($ticket, $author, $recipient, $emisor);

        return [
            'mensaje'    => $mensaje->fresh(['usuario.rol']),
            'email'      => $emailResult,
            'old_status' => $oldStatus,
            'new_status' => $ticket->status,
        ];
    }

    /**
     * Decide el tipo de emisor y valida que el usuario pueda responder el ticket.
     *
     * @throws TicketReplyException
     */
    public function resolveEmisor(Ticket $ticket, User $author): string
    {
        if ((string) $ticket->solicitante_ci === (string) $author->ci) {
            return self::EMISOR_CLIENTE;
        }

        $rol = strtoupper($author->rol?->nombre ?? '');
        $isAdmin = in_array($rol, ['DIRECTOR', 'ADMINISTRADOR', 'ADMIN'], true);
        $isAssignee = (string) $ticket->tecnico_asignado_ci === (string) $author->ci;

        if ($isAdmin || $isAssignee) {
            return self::EMISOR_SOPORTE;
        }

        throw new TicketReplyException(
            'Solo el solicitante, el técnico asignado o la Dirección pueden responder este ticket.',
            'reply_forbidden',
            403
        );
    }

    public function automaticTargetStatus(string $currentStatus, string $emisor): ?string
    {
        if ($emisor === self::EMISOR_SOPORTE) {
            return in_array($currentStatus, [TicketState::STATUS_OPEN, TicketState::STATUS_IN_PROGRESS], true)
                ? TicketState::STATUS_WAITING
                : null;
        }

        // El cliente respondió lo que el soporte le pidió: vuelve a la cola del técnico.
        return $currentStatus === TicketState::STATUS_WAITING ? TicketState::STATUS_IN_PROGRESS : null;
    }

    /**
     * @throws TicketReplyException
     */
    protected function ensureTicketAcceptsReplies(string $status): void
    {
        if (in_array($status, [TicketState::STATUS_RESOLVED, TicketState::STATUS_CLOSED], true)) {
            throw new TicketReplyException(
                'El ticket está resuelto o cerrado y ya no admite respuestas.',
                'ticket_not_replyable',
                422
            );
        }
    }

    protected function historyComment(User $author, string $emisor, string $targetStatus, string $canal = self::CANAL_WEB): string
    {
        $who = $emisor === self::EMISOR_SOPORTE ? 'Soporte' : 'Solicitante';
        $via = $canal === self::CANAL_EMAIL ? ' por correo' : '';
        $label = \App\States\Ticket\TicketStateFactory::makeByKey($targetStatus, new Ticket())->label();

        return "Respuesta registrada{$via} por {$who} ({$author->name}). Estado actualizado automáticamente a '{$label}'.";
    }

    /**
     * Envía la respuesta por correo usando el MailSender inyectado y guarda el
     * Message-ID para mantener el hilo de conversación del ticket.
     */
    protected function sendReplyEmail(Ticket $ticket, TicketMensaje $mensaje, User $author, ?User $recipient): ?MailSendResult
    {
        if (!$recipient || empty($recipient->correo)) {
            return MailSendResult::failed($this->mailSender->driverName(), 'El destinatario no tiene correo registrado.');
        }

        try {
            $sender = $this->mailService->getSender();
            $previousIds = TicketMensaje::where('ticket_codigo', $ticket->codigo)
                ->where('id', '!=', $mensaje->id)
                ->whereNotNull('correo_mensaje_id')
                ->orderBy('fecha_creacion')
                ->orderBy('id')
                ->pluck('correo_mensaje_id')
                ->all();

            // Si la respuesta llegó por correo, el reenvío a la contraparte responde a ese correo.
            if ($mensaje->canal_origen === self::CANAL_EMAIL && $mensaje->correo_mensaje_id) {
                $previousIds[] = $mensaje->correo_mensaje_id;
            }

            $references = array_merge(array_filter([$ticket->correo_hilo]), $previousIds);
            $inReplyTo = end($previousIds) ?: $ticket->correo_hilo;

            $subject = "Re: [{$ticket->codigo}] {$ticket->asunto}";
            $data = [
                'subject'       => $subject,
                'recipientName' => $recipient->name,
                'authorName'    => $author->name,
                'esSoporte'     => $mensaje->tipo_emisor === self::EMISOR_SOPORTE,
                'ticket'        => $ticket,
                'statusLabel'   => $ticket->state()->label(),
                'cuerpo'        => $mensaje->cuerpo_mensaje,
                'actionUrl'     => $this->mailService->getFrontendUrl() . '/tickets/' . $ticket->id,
                'replyByEmail'  => $this->mailReceiver->mailboxAddress() !== null,
            ];

            $mail = OutgoingMail::make()
                ->from($sender['address'], $sender['name'])
                ->to($recipient->correo, $recipient->name)
                ->subject($subject)
                ->html(view('emails.respuesta_ticket', $data)->render())
                ->text("{$author->name} respondió el ticket {$ticket->codigo}:\n\n{$mensaje->cuerpo_mensaje}\n\nVer ticket: {$data['actionUrl']}")
                ->inReplyTo($inReplyTo ?: null)
                ->references($references);

            // Con la recepción activa, las respuestas deben llegar al buzón que lee el sistema.
            if ($inbox = $this->mailReceiver->mailboxAddress()) {
                $mail->replyTo($inbox, $sender['name']);
            }

            $result = $this->mailSender->send($mail);
        } catch (\Throwable $e) {
            Log::warning("Error al preparar el correo de respuesta del ticket {$ticket->codigo}: " . $e->getMessage());
            $result = MailSendResult::failed($this->mailSender->driverName(), $e->getMessage());
        }

        if ($result->success && $result->messageId) {
            // Un mensaje recibido por correo conserva su propio Message-ID.
            if (empty($mensaje->correo_mensaje_id)) {
                $mensaje->correo_mensaje_id = $result->messageId;
                $mensaje->save();
            }

            if (empty($ticket->correo_hilo)) {
                $ticket->correo_hilo = $result->messageId;
                $ticket->save();
            }
        }

        return $result;
    }

    protected function notifyInApp(Ticket $ticket, User $author, ?User $recipient, string $emisor): void
    {
        if (!$recipient || (string) $recipient->ci === (string) $author->ci) {
            return;
        }

        try {
            $this->notifications->createInApp(
                $recipient,
                Notificacion::TIPO_RESPUESTA_TICKET,
                $emisor === self::EMISOR_SOPORTE
                    ? "Soporte respondió tu ticket {$ticket->codigo}"
                    : "Nueva respuesta del solicitante en {$ticket->codigo}",
                "{$author->name} respondió el ticket '{$ticket->asunto}'.",
                $ticket->codigo,
                ['codigo' => $ticket->codigo, 'estado' => $ticket->status]
            );
        } catch (\Throwable $e) {
            Log::warning("Error al crear notificación in-app de respuesta: " . $e->getMessage());
        }
    }
}
