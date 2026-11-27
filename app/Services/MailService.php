<?php

namespace App\Services;

use App\Contracts\Mail\MailReceiver;
use App\Contracts\Mail\MailSender;
use App\Models\Asignacion;
use App\Models\Configuracion;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Mail\OutgoingMail;
use Illuminate\Support\Facades\Log;

class MailService
{
    /**
     * Servidor de correo inyectado (Gmail, SMTP propio, Log...). Ver MailServiceProvider.
     */
    protected MailSender $sender;

    /**
     * Buzón de recepción (si está activo, las respuestas a cualquier correo
     * del sistema llegan ahí y se registran en el ticket).
     */
    protected MailReceiver $receiver;

    public function __construct(MailSender $sender, MailReceiver $receiver)
    {
        $this->sender = $sender;
        $this->receiver = $receiver;
    }

    /**
     * URL base de la aplicación Frontend para los botones de acción.
     */
    public function getFrontendUrl(): string
    {
        return env('FRONTEND_URL', 'http://localhost:5173');
    }

    /**
     * Obtiene el remitente configurado dinámicamente o por variables de entorno.
     */
    public function getSender(): array
    {
        $address = Configuracion::get('correo_remitente_direccion', config('mail.from.address') ?: 'soporte@uagrm.edu.bo');
        $name = Configuracion::get('correo_remitente_nombre', config('mail.from.name') ?: 'Mesa de Ayuda UAGRM');

        return [
            'address' => $address ?: 'soporte@uagrm.edu.bo',
            'name'    => $name ?: 'Mesa de Ayuda UAGRM'
        ];
    }

    /**
     * Verifica si el canal de correo está habilitado globalmente.
     */
    protected function isMailActive(): bool
    {
        return (bool) Configuracion::get('notificaciones_email_activas', true);
    }

    /**
     * Notificación de Apertura de Nuevo Ticket.
     */
    public function sendTicketCreated(Ticket $ticket, array $recipients): void
    {
        if (!$this->isMailActive()) return;
        if (!Configuracion::allowsNotification('TICKET_CREADO', 'email')) return;

        $sender = $this->getSender();
        $actionUrl = $this->getFrontendUrl() . '/tickets/' . $ticket->id;

        foreach ($recipients as $recipient) {
            if (!$recipient || empty($recipient->correo)) continue;

            $data = [
                'subject'       => "[Nuevo Ticket] {$ticket->codigo}: {$ticket->asunto}",
                'recipientName' => $recipient->name,
                'ticket'        => $ticket,
                'actionUrl'     => $actionUrl,
            ];

            $this->sendMailSafe('emails.ticket_creado', $data, $recipient->correo, $recipient->name, $data['subject'], $sender);
        }
    }

    /**
     * Notificación de Cambio de Estado de Ticket.
     */
    public function sendStatusChanged(Ticket $ticket, string $oldStatus, string $newStatus, User $changer, ?string $comments, array $recipients): void
    {
        if (!$this->isMailActive()) return;
        if (!Configuracion::allowsNotification('CAMBIO_ESTADO', 'email')) return;

        $sender = $this->getSender();
        $actionUrl = $this->getFrontendUrl() . '/tickets/' . $ticket->id;

        foreach ($recipients as $recipient) {
            if (!$recipient || empty($recipient->correo)) continue;

            $data = [
                'subject'       => "[Actualización] {$ticket->codigo} cambió a estado {$newStatus}",
                'recipientName' => $recipient->name,
                'ticket'        => $ticket,
                'oldStatus'     => $oldStatus,
                'newStatus'     => $newStatus,
                'changerName'   => $changer->name,
                'comments'      => $comments,
                'actionUrl'     => $actionUrl,
            ];

            $this->sendMailSafe('emails.cambio_estado', $data, $recipient->correo, $recipient->name, $data['subject'], $sender);
        }
    }

    /**
     * Notificación de Solicitud de Reasignación enviada al Administrador.
     */
    public function sendReassignmentRequested(Ticket $ticket, Asignacion $reasignacion, array $admins): void
    {
        if (!$this->isMailActive()) return;
        if (!Configuracion::allowsNotification('SOLICITUD_REASIGNACION', 'email')) return;

        $reasignacion->loadMissing('participantes.usuario');
        $sender = $this->getSender();
        $actionUrl = $this->getFrontendUrl() . '/tickets/' . $ticket->id;
        $solicitanteName = ($reasignacion->solicitante() ?? $ticket->assignee)?->name ?? 'Técnico de soporte';
        $anteriorName = ($reasignacion->tecnicoOrigen() ?? $ticket->assignee)?->name ?? $solicitanteName;
        $propuestoName = $reasignacion->tecnicoDestino()?->name ?? 'Técnico propuesto';

        foreach ($admins as $admin) {
            if (!$admin || empty($admin->correo)) continue;

            $data = [
                'subject'               => "[Solicitud Reasignación] Ticket {$ticket->codigo}",
                'ticket'                => $ticket,
                'solicitanteName'       => $solicitanteName,
                'tecnicoAnteriorName'   => $anteriorName,
                'tecnicoPropuestoName'  => $propuestoName,
                'motivo'                => $reasignacion->motivo_solicitud,
                'fechaSolicitud'        => $reasignacion->fecha_creacion ? $reasignacion->fecha_creacion->format('d/m/Y H:i') : now()->format('d/m/Y H:i'),
                'actionUrl'             => $actionUrl,
            ];

            $this->sendMailSafe('emails.solicitud_reasignacion', $data, $admin->correo, $admin->name, $data['subject'], $sender);
        }
    }

    /**
     * Notificación de Reasignación Aprobada (Técnico y Creador del ticket).
     */
    public function sendReassignmentApproved(Ticket $ticket, Asignacion $reasignacion, User $newAgent, User $creator, User $revisor): void
    {
        if (!$this->isMailActive()) return;
        if (!Configuracion::allowsNotification('REASIGNACION', 'email')) return;

        $sender = $this->getSender();
        $actionUrl = $this->getFrontendUrl() . '/tickets/' . $ticket->id;
        $recipients = array_filter([$newAgent, $creator]);

        foreach ($recipients as $recipient) {
            if (!$recipient || empty($recipient->correo)) continue;

            $data = [
                'subject'          => "[Reasignación Aprobada] Ticket {$ticket->codigo} reasignado",
                'recipientName'    => $recipient->name,
                'ticket'           => $ticket,
                'nuevoTecnicoName' => $newAgent->name,
                'revisorName'      => $revisor->name,
                'motivo'           => $reasignacion->motivo_solicitud,
                'notaResolucion'   => $reasignacion->motivo_resolucion,
                'actionUrl'        => $actionUrl,
            ];

            $this->sendMailSafe('emails.reasignacion_aprobada', $data, $recipient->correo, $recipient->name, $data['subject'], $sender);
        }
    }

    /**
     * Notificación de Reasignación Rechazada (Solo al técnico solicitante).
     */
    public function sendReassignmentRejected(Ticket $ticket, Asignacion $reasignacion, User $requestingTechnician, User $revisor): void
    {
        if (!$this->isMailActive()) return;
        if (!Configuracion::allowsNotification('REASIGNACION', 'email')) return;

        if (empty($requestingTechnician->correo)) return;

        $sender = $this->getSender();
        $actionUrl = $this->getFrontendUrl() . '/tickets/' . $ticket->id;

        $data = [
            'subject'        => "[Reasignación Rechazada] Ticket {$ticket->codigo}",
            'recipientName'  => $requestingTechnician->name,
            'ticket'         => $ticket,
            'revisorName'    => $revisor->name,
            'motivoRechazo'  => $reasignacion->motivo_resolucion,
            'actionUrl'      => $actionUrl,
        ];

        $this->sendMailSafe('emails.reasignacion_rechazada', $data, $requestingTechnician->correo, $requestingTechnician->name, $data['subject'], $sender);
    }

    /**
     * Recordatorio individual de ticket pendiente.
     */
    public function sendPendingTicketReminder(User $recipient, Ticket $ticket, int $hoursPending): void
    {
        if (!$this->isMailActive()) return;
        if (!Configuracion::allowsNotification('RECORDATORIO_PENDIENTE', 'email')) return;
        if (empty($recipient->correo)) return;

        $sender = $this->getSender();
        $actionUrl = $this->getFrontendUrl() . '/tickets/' . $ticket->id;

        $data = [
            'subject'        => "[Recordatorio SLA] Ticket {$ticket->codigo} pendiente de atención",
            'recipientName'  => $recipient->name,
            'ticket'         => $ticket,
            'horasPendiente' => $hoursPending,
            'actionUrl'      => $actionUrl,
        ];

        $this->sendMailSafe('emails.recordatorio_pendientes', $data, $recipient->correo, $recipient->name, $data['subject'], $sender);
    }

    /**
     * Resumen diario (Digest) de tickets pendientes para Técnicos y Directores (S3).
     */
    public function sendPendingDigest(User $recipient, $tickets): void
    {
        if (!$this->isMailActive()) return;
        if (!Configuracion::allowsNotification('RECORDATORIO_PENDIENTE', 'email')) return;
        if (empty($recipient->correo) || $tickets->isEmpty()) return;

        $sender = $this->getSender();
        $actionUrl = $this->getFrontendUrl() . '/tickets';

        $data = [
            'subject'       => "[Resumen Diario] " . count($tickets) . " tickets pendientes de atención",
            'recipientName' => $recipient->name,
            'tickets'       => $tickets,
            'actionUrl'     => $actionUrl,
        ];

        $this->sendMailSafe('emails.resumen_digest', $data, $recipient->correo, $recipient->name, $data['subject'], $sender);
    }

    /**
     * Notificación cuando un ticket lleva X días sin respuesta (S2).
     */
    public function sendTicketNoResponseAlert(Ticket $ticket, User $solicitante, int $days): void
    {
        if (!$this->isMailActive()) return;
        if (!Configuracion::allowsNotification('TICKET_SIN_RESPUESTA', 'email')) return;
        if (empty($solicitante->correo)) return;

        $sender = $this->getSender();
        $actionUrl = $this->getFrontendUrl() . '/tickets/' . $ticket->id;

        $data = [
            'subject'           => "[Seguimiento Ticket] Estado de tu solicitud {$ticket->codigo}",
            'recipientName'     => $solicitante->name,
            'ticket'            => $ticket,
            'diasSinRespuesta'  => $days,
            'actionUrl'         => $actionUrl,
        ];

        $this->sendMailSafe('emails.ticket_sin_respuesta', $data, $solicitante->correo, $solicitante->name, $data['subject'], $sender);
    }

    /**
     * Notificación de Relevamiento / Devolución de ticket.
     * - Receptor: detalle completo (quién lo relevó, cargo destino y motivo).
     * - Solicitante: aviso genérico sin datos internos de la estructura.
     */
    public function sendTicketEscalated(Ticket $ticket, Asignacion $movimiento, User $actor, User $receptor, ?User $solicitante): void
    {
        if (!$this->isMailActive()) return;
        if (!Configuracion::allowsNotification('TICKET_RELEVADO', 'email')) return;

        $sender = $this->getSender();
        $actionUrl = $this->getFrontendUrl() . '/tickets/' . $ticket->id;
        $esDevolucion = $movimiento->tipo_origen === Asignacion::TIPO_DEVOLUCION;

        if (!empty($receptor->correo) && $receptor->id !== $actor->id) {
            $data = [
                'subject'       => ($esDevolucion ? '[Ticket Devuelto] ' : '[Ticket Relevado] ') . "Ticket {$ticket->codigo}",
                'recipientName' => $receptor->name,
                'ticket'        => $ticket,
                'esSolicitante' => false,
                'esDevolucion'  => $esDevolucion,
                'tipo'          => $movimiento->tipo_origen,
                'actorName'     => $actor->name,
                'cargo'         => $movimiento->cargoDestino,
                'motivo'        => $movimiento->motivo_solicitud,
                'actionUrl'     => $actionUrl,
            ];
            $this->sendMailSafe('emails.ticket_relevado', $data, $receptor->correo, $receptor->name, $data['subject'], $sender);
        }

        if ($solicitante && !empty($solicitante->correo) && $solicitante->id !== $actor->id && $solicitante->id !== $receptor->id) {
            $data = [
                'subject'       => "[Actualización] Tu ticket {$ticket->codigo} fue derivado",
                'recipientName' => $solicitante->name,
                'ticket'        => $ticket,
                'esSolicitante' => true,
                'esDevolucion'  => $esDevolucion,
                'actionUrl'     => $actionUrl,
            ];
            $this->sendMailSafe('emails.ticket_relevado', $data, $solicitante->correo, $solicitante->name, $data['subject'], $sender);
        }
    }

    /**
     * Envío seguro: renderiza la vista Blade y delega el envío al MailSender inyectado.
     */
    protected function sendMailSafe(string $view, array $data, string $toEmail, string $toName, string $subject, array $sender): bool
    {
        try {
            $mail = OutgoingMail::make()
                ->from($sender['address'], $sender['name'])
                ->to($toEmail, $toName)
                ->subject($subject)
                ->html(view($view, $data)->render());

            if ($inbox = $this->receiver->mailboxAddress()) {
                $mail->replyTo($inbox, $sender['name']);
            }

            $result = $this->sender->send($mail);

            if (!$result->success) {
                Log::warning("No se pudo enviar correo a {$toEmail} ({$subject}) vía {$result->driver}: {$result->error}");
            }

            return $result->success;
        } catch (\Throwable $e) {
            Log::warning("No se pudo enviar correo a {$toEmail} ({$subject}): " . $e->getMessage());
            return false;
        }
    }
}
