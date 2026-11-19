<?php

namespace App\Services;

use App\Models\Configuracion;
use App\Models\Notificacion;
use App\Models\Ticket;
use App\Models\TicketAsignacion;
use App\Models\User;
use Illuminate\Support\Collection;

class NotificationService
{
    protected MailService $mailService;

    public function __construct(MailService $mailService)
    {
        $this->mailService = $mailService;
    }

    /**
     * Obtiene todos los administradores (Directores) activos.
     */
    public function getAdmins(): Collection
    {
        return User::whereHas('rol', function ($q) {
            $q->whereIn('nombre', ['DIRECTOR', 'ADMINISTRADOR', 'ADMIN']);
        })->where('activo', true)->get();
    }

    /**
     * Verifica si el canal In-App está activo globalmente.
     */
    protected function isInAppActive(): bool
    {
        return (bool) Configuracion::get('notificaciones_inapp_activas', true);
    }

    /**
     * Crea un registro de notificación en la tabla 'notificaciones' si está permitido.
     */
    public function createInApp(User $user, string $tipo, string $titulo, string $mensaje, ?int $ticketId = null, ?array $datos = null): ?Notificacion
    {
        if (!$this->isInAppActive()) {
            return null;
        }

        if (!Configuracion::allowsNotification($tipo, 'in_app')) {
            return null;
        }

        return Notificacion::create([
            'usuario_id'          => $user->id,
            'tipo'                => $tipo,
            'titulo'              => $titulo,
            'mensaje'             => $mensaje,
            'ticket_id'           => $ticketId,
            'datos'               => $datos,
            'leida'               => false,
            'fecha_creacion'      => now(),
            'fecha_actualizacion' => now(),
        ]);
    }

    /**
     * PROCESO 1: Apertura de nuevo Ticket.
     * - Email: Solicitante, Técnico asignado (si existe) y Administradores.
     * - In-App: Técnico asignado y Administradores.
     */
    public function notifyTicketCreated(Ticket $ticket): void
    {
        $ticket->loadMissing(['assignee', 'creator', 'prioridad', 'categorias']);
        $admins = $this->getAdmins();

        // 1. Envío In-App (Técnico y Administradores)
        if ($ticket->assignee) {
            $this->createInApp(
                $ticket->assignee,
                Notificacion::TIPO_TICKET_CREADO,
                "Nuevo ticket asignado: {$ticket->codigo}",
                "Se te ha asignado el ticket '{$ticket->asunto}' con prioridad {$ticket->priority}.",
                $ticket->id,
                ['codigo' => $ticket->codigo, 'prioridad' => $ticket->priority]
            );
        }

        foreach ($admins as $admin) {
            $this->createInApp(
                $admin,
                Notificacion::TIPO_TICKET_CREADO,
                "Nuevo ticket creado: {$ticket->codigo}",
                "El usuario {$ticket->creator?->name} ha creado el ticket '{$ticket->asunto}'.",
                $ticket->id,
                ['codigo' => $ticket->codigo, 'solicitante' => $ticket->creator?->name]
            );
        }

        // 2. Envío por Correo Electrónico
        $emailRecipients = [];
        if ($ticket->creator) $emailRecipients[] = $ticket->creator;
        if ($ticket->assignee) $emailRecipients[] = $ticket->assignee;
        foreach ($admins as $admin) {
            $emailRecipients[] = $admin;
        }

        // Evitar duplicados por id
        $uniqueRecipients = collect($emailRecipients)->unique('id')->all();
        $this->mailService->sendTicketCreated($ticket, $uniqueRecipients);
    }

    /**
     * PROCESO 2: Cambio de Estado del Ticket.
     * - Email: Solicitante, Técnico asignado y Administradores.
     * - In-App: Técnico asignado y Administradores.
     */
    public function notifyStatusChanged(Ticket $ticket, string $oldStatus, string $newStatus, User $changer, ?string $comments = null): void
    {
        $ticket->loadMissing(['assignee', 'creator']);
        $admins = $this->getAdmins();

        // 1. In-App para Técnico
        if ($ticket->assignee && $ticket->assignee->id !== $changer->id) {
            $this->createInApp(
                $ticket->assignee,
                Notificacion::TIPO_CAMBIO_ESTADO,
                "Ticket {$ticket->codigo} actualizado",
                "El ticket cambió de estado a '{$newStatus}' por {$changer->name}.",
                $ticket->id,
                ['codigo' => $ticket->codigo, 'nuevo_estado' => $newStatus]
            );
        }

        // In-App para Administradores
        foreach ($admins as $admin) {
            if ($admin->id !== $changer->id) {
                $this->createInApp(
                    $admin,
                    Notificacion::TIPO_CAMBIO_ESTADO,
                    "Estado actualizado: {$ticket->codigo}",
                    "El ticket {$ticket->codigo} cambió a '{$newStatus}'.",
                    $ticket->id,
                    ['codigo' => $ticket->codigo, 'nuevo_estado' => $newStatus, 'cambiado_por' => $changer->name]
                );
            }
        }

        // 2. Correo electrónico para todos los involucrados
        $emailRecipients = [];
        if ($ticket->creator) $emailRecipients[] = $ticket->creator;
        if ($ticket->assignee) $emailRecipients[] = $ticket->assignee;
        foreach ($admins as $admin) {
            $emailRecipients[] = $admin;
        }

        $uniqueRecipients = collect($emailRecipients)->unique('id')->all();
        $this->mailService->sendStatusChanged($ticket, $oldStatus, $newStatus, $changer, $comments, $uniqueRecipients);
    }

    /**
     * PROCESO 4a: Solicitud de Reasignación enviada por un técnico.
     * - Email + In-App: Administradores.
     */
    public function notifyReassignmentRequested(Ticket $ticket, TicketAsignacion $reasignacion): void
    {
        $ticket->loadMissing(['assignee']);
        $reasignacion->loadMissing(['tecnico']);
        $admins = $this->getAdmins();

        foreach ($admins as $admin) {
            $this->createInApp(
                $admin,
                Notificacion::TIPO_SOLICITUD_REASIGNACION,
                "Solicitud de Reasignación: {$ticket->codigo}",
                "{$ticket->assignee?->name} solicitó reasignar el ticket a {$reasignacion->tecnico?->name}.",
                $ticket->id,
                ['ticket_id' => $ticket->id, 'reasignacion_id' => $reasignacion->id]
            );
        }

        $this->mailService->sendReassignmentRequested($ticket, $reasignacion, $admins->all());
    }

    /**
     * PROCESO 4b: Reasignación Aprobada por Administrador.
     * - Email: Creador del ticket y Nuevo Técnico.
     * - In-App: Nuevo Técnico y Técnico Anterior.
     */
    public function notifyReassignmentApproved(Ticket $ticket, TicketAsignacion $reasignacion, User $newAgent, User $revisor): void
    {
        $ticket->loadMissing(['creator']);

        // In-App para nuevo técnico
        $this->createInApp(
            $newAgent,
            Notificacion::TIPO_REASIGNACION_APROBADA,
            "Ticket Reasignado a tu cargo: {$ticket->codigo}",
            "La Dirección te ha asignado el ticket {$ticket->codigo}. Motivo: {$reasignacion->motivo}.",
            $ticket->id
        );

        $this->mailService->sendReassignmentApproved($ticket, $reasignacion, $newAgent, $ticket->creator, $revisor);
    }

    /**
     * PROCESO 4c: Reasignación Rechazada por Administrador.
     * - Email + In-App: Técnico solicitante.
     */
    public function notifyReassignmentRejected(Ticket $ticket, TicketAsignacion $reasignacion, User $requestingTechnician, User $revisor): void
    {
        // Notificación In-App para el técnico que solicitó (como requirió el usuario)
        $this->createInApp(
            $requestingTechnician,
            Notificacion::TIPO_REASIGNACION_RECHAZADA,
            "Reasignación Denegada: {$ticket->codigo}",
            "Tu solicitud de reasignación fue rechazada. Motivo: {$reasignacion->motivo_resolucion}",
            $ticket->id,
            ['motivo' => $reasignacion->motivo_resolucion]
        );

        // Correo electrónico
        $this->mailService->sendReassignmentRejected($ticket, $reasignacion, $requestingTechnician, $revisor);
    }

    /**
     * PROCESO 3: Recordatorio de Tickets Pendientes.
     * Notificación In-App y Email para un técnico o administrador.
     */
    public function notifyPendingReminder(User $recipient, Ticket $ticket, int $hours): void
    {
        $this->createInApp(
            $recipient,
            Notificacion::TIPO_RECORDATORIO_PENDIENTE,
            "Recordatorio de Ticket Pendiente: {$ticket->codigo}",
            "El ticket '{$ticket->asunto}' lleva más de {$hours} horas sin actualización.",
            $ticket->id,
            ['horas' => $hours]
        );

        $this->mailService->sendPendingTicketReminder($recipient, $ticket, $hours);
    }

    /**
     * Notificación de Alerta cuando un ticket lleva X días sin respuesta (S2).
     */
    public function notifyNoResponse(Ticket $ticket, int $days): void
    {
        $ticket->loadMissing('creator');
        if (!$ticket->creator) return;

        // Si el solicitante es técnico o admin, también crear in-app
        $rolNombre = strtoupper($ticket->creator->rol?->nombre ?? '');
        if (in_array($rolNombre, ['DIRECTOR', 'TECNICO', 'ADMINISTRADOR'])) {
            $this->createInApp(
                $ticket->creator,
                Notificacion::TIPO_TICKET_SIN_RESPUESTA,
                "Seguimiento de Ticket: {$ticket->codigo}",
                "Tu ticket lleva {$days} días en proceso de atención. Seguimos trabajando en él.",
                $ticket->id
            );
        }

        $this->mailService->sendTicketNoResponseAlert($ticket, $ticket->creator, $days);
    }
}
