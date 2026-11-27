<?php

namespace Database\Seeders;

use App\Models\Configuracion;
use Illuminate\Database\Seeder;

class ConfiguracionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $configs = [
            [
                'clave'       => 'horas_recordatorio_pendientes',
                'valor'       => '24',
                'tipo'        => 'integer',
                'grupo'       => 'notificaciones',
                'descripcion' => 'Horas de inactividad para enviar recordatorio de tickets pendientes al técnico y administrador.',
            ],
            [
                'clave'       => 'dias_ticket_sin_respuesta',
                'valor'       => '3',
                'tipo'        => 'integer',
                'grupo'       => 'notificaciones',
                'descripcion' => 'Días transcurridos sin respuesta para notificar al solicitante que su ticket sigue en proceso.',
            ],
            [
                'clave'       => 'resumen_diario_activo',
                'valor'       => '1',
                'tipo'        => 'boolean',
                'grupo'       => 'notificaciones',
                'descripcion' => 'Activar el envío de un correo diario de resumen (digest) con los tickets pendientes consolidados.',
            ],
            [
                'clave'       => 'notificaciones_email_activas',
                'valor'       => '1',
                'tipo'        => 'boolean',
                'grupo'       => 'notificaciones',
                'descripcion' => 'Habilitar o pausar globalmente el envío de notificaciones por correo electrónico.',
            ],
            [
                'clave'       => 'notificaciones_inapp_activas',
                'valor'       => '1',
                'tipo'        => 'boolean',
                'grupo'       => 'notificaciones',
                'descripcion' => 'Habilitar o pausar globalmente la bandeja de notificaciones en la plataforma web.',
            ],
            [
                'clave'       => 'correo_remitente_nombre',
                'valor'       => 'Mesa de Ayuda UAGRM',
                'tipo'        => 'string',
                'grupo'       => 'notificaciones',
                'descripcion' => 'Nombre que aparecerá como remitente de las notificaciones por correo.',
            ],
            [
                'clave'       => 'correo_remitente_direccion',
                'valor'       => 'soporte@uagrm.edu.bo',
                'tipo'        => 'string',
                'grupo'       => 'notificaciones',
                'descripcion' => 'Dirección de correo remitente para el envío de notificaciones del sistema.',
            ],
            // Políticas institucionales por evento (Correo & Bandeja Plataforma)
            [
                'clave'       => 'notif_evento_ticket_creado_email',
                'valor'       => '1',
                'tipo'        => 'boolean',
                'grupo'       => 'notificaciones',
                'descripcion' => 'Enviar correo institucional ante la creación o apertura de un ticket.',
            ],
            [
                'clave'       => 'notif_evento_ticket_creado_inapp',
                'valor'       => '1',
                'tipo'        => 'boolean',
                'grupo'       => 'notificaciones',
                'descripcion' => 'Registrar notificación en plataforma web ante la creación de un ticket.',
            ],
            [
                'clave'       => 'notif_evento_cambio_estado_email',
                'valor'       => '1',
                'tipo'        => 'boolean',
                'grupo'       => 'notificaciones',
                'descripcion' => 'Enviar correo institucional ante cambios de estado de un ticket.',
            ],
            [
                'clave'       => 'notif_evento_cambio_estado_inapp',
                'valor'       => '1',
                'tipo'        => 'boolean',
                'grupo'       => 'notificaciones',
                'descripcion' => 'Registrar notificación en plataforma web ante cambios de estado.',
            ],
            [
                'clave'       => 'notif_evento_solicitud_reasignacion_email',
                'valor'       => '1',
                'tipo'        => 'boolean',
                'grupo'       => 'notificaciones',
                'descripcion' => 'Enviar correo a la Dirección ante solicitudes de reasignación técnica.',
            ],
            [
                'clave'       => 'notif_evento_solicitud_reasignacion_inapp',
                'valor'       => '1',
                'tipo'        => 'boolean',
                'grupo'       => 'notificaciones',
                'descripcion' => 'Registrar notificación en plataforma web ante solicitudes de reasignación.',
            ],
            [
                'clave'       => 'notif_evento_reasignacion_email',
                'valor'       => '1',
                'tipo'        => 'boolean',
                'grupo'       => 'notificaciones',
                'descripcion' => 'Enviar correo institucional al aprobarse o rechazarse una reasignación.',
            ],
            [
                'clave'       => 'notif_evento_reasignacion_inapp',
                'valor'       => '1',
                'tipo'        => 'boolean',
                'grupo'       => 'notificaciones',
                'descripcion' => 'Registrar notificación en plataforma web ante resolución de reasignaciones.',
            ],
            [
                'clave'       => 'notif_evento_recordatorio_email',
                'valor'       => '1',
                'tipo'        => 'boolean',
                'grupo'       => 'notificaciones',
                'descripcion' => 'Enviar correo institucional de recordatorio para tickets pendientes.',
            ],
            [
                'clave'       => 'notif_evento_recordatorio_inapp',
                'valor'       => '1',
                'tipo'        => 'boolean',
                'grupo'       => 'notificaciones',
                'descripcion' => 'Registrar notificación en plataforma web de recordatorio para tickets pendientes.',
            ],
            [
                'clave'       => 'notif_evento_sin_respuesta_email',
                'valor'       => '1',
                'tipo'        => 'boolean',
                'grupo'       => 'notificaciones',
                'descripcion' => 'Enviar correo de seguimiento al solicitante cuando un ticket no tiene respuesta.',
            ],
            [
                'clave'       => 'notif_evento_sin_respuesta_inapp',
                'valor'       => '1',
                'tipo'        => 'boolean',
                'grupo'       => 'notificaciones',
                'descripcion' => 'Registrar notificación en plataforma web para tickets sin respuesta.',
            ],
            [
                'clave'       => 'relevamiento_permitir_saltar_niveles',
                'valor'       => '0',
                'tipo'        => 'boolean',
                'grupo'       => 'relevamiento',
                'descripcion' => 'Permite a los Técnicos relevar tickets saltando niveles de la estructura (los Administradores siempre pueden). Desactivado = relevamiento secuencial al nivel contiguo.',
            ],
        ];

        $adminCi = \App\Models\User::where('ci', '1000001')->value('ci')
            ?? \App\Models\User::whereHas('rol', fn ($q) => $q->where('nombre', 'DIRECTOR'))->value('ci');

        foreach ($configs as $cfg) {
            $cfg['usuario_ci'] = $adminCi;
            Configuracion::firstOrCreate(
                ['clave' => $cfg['clave']],
                $cfg
            );
        }
    }
}
