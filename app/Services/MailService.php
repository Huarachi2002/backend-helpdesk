<?php

namespace App\Services;

use App\Models\Configuracion;
use App\Models\Ticket;
use App\Models\TicketAsignacion;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MailService
{
    /**
     * URL base de la aplicación Frontend para los botones de acción.
     */
    protected function getFrontendUrl(): string
    {
        return env('FRONTEND_URL', 'http://localhost:5173');
    }

    /**
     * Obtiene el remitente configurado dinámicamente o por variables de entorno.
     */
    protected function getSender(): array
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
    public function sendReassignmentRequested(Ticket $ticket, TicketAsignacion $reasignacion, array $admins): void
    {
        if (!$this->isMailActive()) return;
        if (!Configuracion::allowsNotification('SOLICITUD_REASIGNACION', 'email')) return;

        $sender = $this->getSender();
        $actionUrl = $this->getFrontendUrl() . '/tickets/' . $ticket->id;
        $solicitanteName = $ticket->assignee?->name ?? 'Técnico de soporte';
        $propuestoName = $reasignacion->tecnico?->name ?? 'Técnico propuesto';

        foreach ($admins as $admin) {
            if (!$admin || empty($admin->correo)) continue;

            $data = [
                'subject'               => "[Solicitud Reasignación] Ticket {$ticket->codigo}",
                'ticket'                => $ticket,
                'solicitanteName'       => $solicitanteName,
                'tecnicoAnteriorName'   => $solicitanteName,
                'tecnicoPropuestoName'  => $propuestoName,
                'motivo'                => $reasignacion->motivo,
                'fechaSolicitud'        => $reasignacion->fecha_inicio ? $reasignacion->fecha_inicio->format('d/m/Y H:i') : now()->format('d/m/Y H:i'),
                'actionUrl'             => $actionUrl,
            ];

            $this->sendMailSafe('emails.solicitud_reasignacion', $data, $admin->correo, $admin->name, $data['subject'], $sender);
        }
    }

    /**
     * Notificación de Reasignación Aprobada (Técnico y Creador del ticket).
     */
    public function sendReassignmentApproved(Ticket $ticket, TicketAsignacion $reasignacion, User $newAgent, User $creator, User $revisor): void
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
                'motivo'           => $reasignacion->motivo,
                'notaResolucion'   => $reasignacion->motivo_resolucion,
                'actionUrl'        => $actionUrl,
            ];

            $this->sendMailSafe('emails.reasignacion_aprobada', $data, $recipient->correo, $recipient->name, $data['subject'], $sender);
        }
    }

    /**
     * Notificación de Reasignación Rechazada (Solo al técnico solicitante).
     */
    public function sendReassignmentRejected(Ticket $ticket, TicketAsignacion $reasignacion, User $requestingTechnician, User $revisor): void
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
     * Envío seguro con manejo de excepciones y log.
     */
    protected function sendMailSafe(string $view, array $data, string $toEmail, string $toName, string $subject, array $sender): bool
    {
        try {
            Mail::send($view, $data, function ($message) use ($toEmail, $toName, $subject, $sender) {
                $message->from($sender['address'], $sender['name']);
                $message->to($toEmail, $toName);
                $message->subject($subject);
            });
            return true;
        } catch (\Throwable $e) {
            Log::warning("No se pudo enviar correo a {$toEmail} ({$subject}): " . $e->getMessage());
            return false;
        }
    }
}
