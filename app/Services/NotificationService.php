<?php

namespace App\Services;

use App\Models\Asignacion;
use App\Models\Configuracion;
use App\Models\Notificacion;
use App\Models\Ticket;
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
    public function createInApp(User $user, string $tipo, string $titulo, string $mensaje, ?string $ticketCodigo = null, ?array $datos = null): ?Notificacion
    {
        if (!$this->isInAppActive()) {
            return null;
        }

        if (!Configuracion::allowsNotification($tipo, 'in_app')) {
            return null;
        }

        return Notificacion::create([
            'usuario_ci'          => $user->ci,
            'tipo'                => $tipo,
            'titulo'              => $titulo,
            'mensaje'             => $mensaje,
            'ticket_codigo'       => $ticketCodigo,
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
     * - Email + In-App: Administradores / Cadena superior.
     */
    public function notifyReassignmentRequested(Ticket $ticket, Asignacion $reasignacion): void
    {
        $ticket->loadMissing(['assignee']);
        $reasignacion->loadMissing(['participantes.usuario']);
        $solicitante = $reasignacion->solicitante() ?? $ticket->assignee;
        $propuesto = $reasignacion->tecnicoDestino();

        // Aprobación jerárquica (D5): se notifica a la cadena superior del técnico solicitante
        $admins = app(ReassignmentApprovalService::class)->approvers($reasignacion, $ticket);

        foreach ($admins as $admin) {
            $this->createInApp(
                $admin,
                Notificacion::TIPO_SOLICITUD_REASIGNACION,
                "Solicitud de Reasignación: {$ticket->codigo}",
                "{$solicitante?->name} solicitó reasignar el ticket a {$propuesto?->name}.",
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
    public function notifyReassignmentApproved(Ticket $ticket, Asignacion $reasignacion, User $newAgent, User $revisor): void
    {
        $ticket->loadMissing(['creator']);

        // In-App para nuevo técnico
        $this->createInApp(
            $newAgent,
            Notificacion::TIPO_REASIGNACION_APROBADA,
            "Ticket Reasignado a tu cargo: {$ticket->codigo}",
            "La Dirección te ha asignado el ticket {$ticket->codigo}. Motivo: {$reasignacion->motivo_solicitud}.",
            $ticket->id
        );

        $this->mailService->sendReassignmentApproved($ticket, $reasignacion, $newAgent, $ticket->creator, $revisor);
    }

    /**
     * PROCESO 4c: Reasignación Rechazada por Administrador.
     * - Email + In-App: Técnico solicitante.
     */
    public function notifyReassignmentRejected(Ticket $ticket, Asignacion $reasignacion, User $requestingTechnician, User $revisor): void
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
     * PROCESO 5: Relevamiento (ascendente/descendente) o Devolución de un ticket.
     * - In-App + Email: usuario receptor.
     * - In-App: responsable anterior y jefes de la cadena superior del cargo destino.
     * - Email + In-App (mensaje genérico, sin datos internos): solicitante del ticket.
     * No se notifica al usuario que ejecutó la acción.
     */
    public function notifyTicketEscalated(Ticket $ticket, Asignacion $movimiento, User $actor, ?User $anterior, User $receptor): void
    {
        $ticket->loadMissing(['creator']);
        $movimiento->loadMissing(['cargoDestino']);
        $cargo = $movimiento->cargoDestino;

        $esDevolucion = $movimiento->tipo_origen === Asignacion::TIPO_DEVOLUCION;
        $tipoNotif = $esDevolucion ? Notificacion::TIPO_TICKET_DEVUELTO : Notificacion::TIPO_TICKET_RELEVADO;
        $accion = match ($movimiento->tipo_origen) {
            Asignacion::TIPO_RELEVAMIENTO_ASCENDENTE  => 'relevado a un nivel superior',
            Asignacion::TIPO_RELEVAMIENTO_DESCENDENTE => 'relevado a un nivel inferior',
            default                                   => 'devuelto al nivel anterior',
        };
        $cargoTxt = $cargo ? "{$cargo->nombre} (Nivel {$cargo->nivel})" : 'otro nivel';
        $datos = [
            'ticket_id'       => $ticket->id,
            'tipo_movimiento' => $movimiento->tipo_origen,
            'cargo_destino'   => $cargo?->codigo,
            'realizado_por'   => $actor->name,
        ];

        $notificados = [$actor->id => true];

        // 1. Receptor
        if (!isset($notificados[$receptor->id])) {
            $this->createInApp(
                $receptor,
                $tipoNotif,
                ($esDevolucion ? 'Ticket devuelto a tu cargo: ' : 'Ticket relevado a tu cargo: ') . $ticket->codigo,
                "{$actor->name} te ha {$accion} el ticket {$ticket->codigo}. " . ($esDevolucion ? 'Instrucciones: ' : 'Motivo: ') . $movimiento->motivo_solicitud,
                $ticket->id,
                $datos
            );
            $notificados[$receptor->id] = true;
        }

        // 2. Responsable anterior
        if ($anterior && !isset($notificados[$anterior->id])) {
            $this->createInApp(
                $anterior,
                $tipoNotif,
                "Ticket {$ticket->codigo} {$accion}",
                "El ticket {$ticket->codigo} fue {$accion} por {$actor->name} y ahora lo atiende {$receptor->name} ({$cargoTxt}).",
                $ticket->id,
                $datos
            );
            $notificados[$anterior->id] = true;
        }

        // 3. Jefes: personal activo de la cadena superior del cargo destino
        if ($cargo) {
            $jefes = User::whereIn('cargo_codigo', $cargo->codigosSuperiores())->where('activo', true)->get();
            foreach ($jefes as $jefe) {
                if (isset($notificados[$jefe->id])) continue;
                $this->createInApp(
                    $jefe,
                    $tipoNotif,
                    "Ticket {$ticket->codigo} {$accion}",
                    "{$actor->name} ha {$accion} el ticket {$ticket->codigo} a {$receptor->name} ({$cargoTxt}).",
                    $ticket->id,
                    $datos
                );
                $notificados[$jefe->id] = true;
            }
        }

        // 4. Solicitante (mensaje genérico, sin nombres ni cargos internos)
        $solicitante = $ticket->creator;
        if ($solicitante && !isset($notificados[$solicitante->id])) {
            $this->createInApp(
                $solicitante,
                $tipoNotif,
                "Actualización de tu ticket {$ticket->codigo}",
                'Tu ticket fue derivado a otro nivel de atención para su resolución.',
                $ticket->id,
                ['ticket_id' => $ticket->id]
            );
        }

        $this->mailService->sendTicketEscalated($ticket, $movimiento, $actor, $receptor, $solicitante);
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
