<?php

namespace App\Console\Commands;

use App\Models\Configuracion;
use App\Models\Notificacion;
use App\Models\Ticket;
use App\Models\User;
use App\Services\MailService;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class EnviarRecordatorioTicketsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tickets:enviar-recordatorios {--force : Forzar envío sin validar horarios}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envía recordatorios automáticos y digest diario de tickets pendientes a técnicos, administradores y solicitantes';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notificationService, MailService $mailService)
    {
        $this->info('Iniciando procesamiento de recordatorios de tickets...');

        // 1. Obtener parámetros configurables desde la base de datos
        $horasPendiente = (int) Configuracion::get('horas_recordatorio_pendientes', 24);
        $diasSinRespuesta = (int) Configuracion::get('dias_ticket_sin_respuesta', 3);
        $resumenDiarioActivo = (bool) Configuracion::get('resumen_diario_activo', true);

        $this->info("Parámetros: Horas límite = {$horasPendiente}h, Días sin respuesta = {$diasSinRespuesta}d, Digest activo = " . ($resumenDiarioActivo ? 'Sí' : 'No'));

        $cutoffTime = now()->subHours($horasPendiente);

        // 2. Buscar tickets pendientes que exceden el tiempo límite sin actualización
        $ticketsPendientes = Ticket::with(['assignee', 'creator', 'prioridad', 'categorias'])
            ->whereIn('estado', ['EN_ESPERA', 'CLIENTE_ESPERANDO_RESPUESTA', 'ESPERANDO_POR_CLIENTE'])
            ->where(function ($q) use ($cutoffTime) {
                $q->where('fecha_actualizacion', '<=', $cutoffTime)
                  ->orWhere(function ($sub) use ($cutoffTime) {
                      $sub->whereNull('fecha_actualizacion')
                          ->where('fecha_creacion', '<=', $cutoffTime);
                  });
            })
            ->get();

        $this->info("Total de tickets pendientes detectados: " . $ticketsPendientes->count());

        if ($ticketsPendientes->isNotEmpty()) {
            // Agrupar por técnico asignado
            $ticketsPorTecnico = $ticketsPendientes->groupBy('tecnico_asignado_id');

            foreach ($ticketsPorTecnico as $tecnicoId => $tickets) {
                if ($tecnicoId && $tecnico = User::find($tecnicoId)) {
                    $this->info("Notificando al técnico {$tecnico->name} ({$tickets->count()} tickets pendientes)");

                    // Notificación In-App en la plataforma
                    $notificationService->createInApp(
                        $tecnico,
                        Notificacion::TIPO_RECORDATORIO_PENDIENTE,
                        "Recordatorio: Tienes {$tickets->count()} tickets pendientes",
                        "Existen tickets asignados a tu cuenta que no presentan actividad desde hace más de {$horasPendiente} horas.",
                        $tickets->first()->id,
                        ['total_pendientes' => $tickets->count(), 'horas_limite' => $horasPendiente]
                    );

                    // Envío por correo (Digest diario consolidado o recordatorio)
                    if ($resumenDiarioActivo) {
                        $mailService->sendPendingDigest($tecnico, $tickets);
                    } else {
                        foreach ($tickets as $ticket) {
                            $mailService->sendPendingTicketReminder($tecnico, $ticket, $horasPendiente);
                        }
                    }
                }
            }

            // Notificar a los administradores sobre tickets sin asignar o panorama general
            $admins = $notificationService->getAdmins();
            $ticketsSinAsignar = $ticketsPendientes->whereNull('tecnico_asignado_id');

            foreach ($admins as $admin) {
                $this->info("Enviando reporte general a administrador {$admin->name}");

                $notificationService->createInApp(
                    $admin,
                    Notificacion::TIPO_RECORDATORIO_PENDIENTE,
                    "Resumen de Mesa de Ayuda: {$ticketsPendientes->count()} tickets pendientes",
                    "Hay {$ticketsPendientes->count()} tickets pendientes de atención (incluyendo {$ticketsSinAsignar->count()} sin técnico asignado).",
                    $ticketsPendientes->first()->id,
                    ['total' => $ticketsPendientes->count(), 'sin_asignar' => $ticketsSinAsignar->count()]
                );

                if ($resumenDiarioActivo) {
                    $mailService->sendPendingDigest($admin, $ticketsPendientes);
                }
            }
        }

        // 3. Notificación a Solicitantes con Tickets sin respuesta inicial (Sugerencia S2)
        $cutoffSinRespuesta = now()->subDays($diasSinRespuesta);
        $ticketsSinRespuesta = Ticket::with(['creator', 'category'])
            ->whereIn('estado', ['EN_ESPERA', 'CLIENTE_ESPERANDO_RESPUESTA'])
            ->whereNull('fecha_respuesta')
            ->where('fecha_creacion', '<=', $cutoffSinRespuesta)
            ->get();

        $this->info("Tickets sin respuesta inicial (> {$diasSinRespuesta} días): " . $ticketsSinRespuesta->count());

        foreach ($ticketsSinRespuesta as $t) {
            $notificationService->notifyNoResponse($t, $diasSinRespuesta);
        }

        $this->info('Proceso de recordatorios finalizado exitosamente.');
        return 0;
    }
}
