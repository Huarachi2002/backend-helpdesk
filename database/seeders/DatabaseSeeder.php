<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // --- Usuarios ---
        $admin = \App\Models\User::create([
            'name'       => 'Admin Helpdesk',
            'email'      => 'admin@helpdesk.com',
            'password'   => bcrypt('password'),
            'role'       => 'admin',
            'department' => 'TI',
        ]);

        $agent1 = \App\Models\User::create([
            'name'       => 'Soporte Juan',
            'email'      => 'juan@helpdesk.com',
            'password'   => bcrypt('password'),
            'role'       => 'support',
            'department' => 'Soporte Técnico',
        ]);

        $agent2 = \App\Models\User::create([
            'name'       => 'Soporte María',
            'email'      => 'maria@helpdesk.com',
            'password'   => bcrypt('password'),
            'role'       => 'support',
            'department' => 'Soporte Académico',
        ]);

        $student = \App\Models\User::create([
            'name'       => 'Estudiante Demo',
            'email'      => 'estudiante@moodle.com',
            'password'   => bcrypt('password'),
            'role'       => 'estudiante',
            'department' => null,
        ]);

        $teacher = \App\Models\User::create([
            'name'       => 'Prof. Carlos Méndez',
            'email'      => 'carlos.mendez@moodle.com',
            'password'   => bcrypt('password'),
            'role'       => 'teacher',
            'department' => 'Facultad de Ingeniería',
        ]);

        // --- Categorías ---
        $categories = [
            ['name' => 'Acceso y Login',      'description' => 'Problemas para ingresar a la plataforma'],
            ['name' => 'Error en Contenido',  'description' => 'Contenido incorrecto o faltante en cursos'],
            ['name' => 'Calificaciones',      'description' => 'Dudas o errores en notas y evaluaciones'],
            ['name' => 'Problemas Técnicos',  'description' => 'Fallas técnicas generales del sistema'],
            ['name' => 'Consulta General',    'description' => 'Preguntas generales de uso de la plataforma'],
        ];

        $catModels = [];
        foreach ($categories as $cat) {
            $catModels[$cat['name']] = \App\Models\TicketCategory::create($cat);
        }

        $catIds = \App\Models\TicketCategory::pluck('id')->toArray();

        // =========================================================================
        // 5 TICKETS REALES CON HISTORIAL DE CONVERSACIÓN LARGA Y FLUJO DE ESTADOS
        // =========================================================================

        // -------------------------------------------------------------------------
        // CASO 1: Error 413 al subir entrega final (Estado actual: Esperando Respuesta)
        // -------------------------------------------------------------------------
        $t1Time = now()->subDays(3);
        $ticket1 = \App\Models\Ticket::create([
            'title'             => 'Error 413 al subir proyecto final en Moodle (Límite excedido)',
            'description'       => 'Buenas tardes equipo de soporte, intento cargar mi archivo ZIP de 18MB con el proyecto final de la materia Ingeniería Web II y la plataforma me arroja "413 Request Entity Too Large". La fecha de cierre es hoy a las 23:59.',
            'status'            => 'waiting',
            'priority'          => 'critical',
            'category_id'       => $catModels['Problemas Técnicos']->id,
            'assigned_to'       => $agent1->id,
            'created_by'        => $student->id,
            'moodle_user_id'    => 'moodle_std_1042',
            'moodle_course'     => 'Ingeniería Web II - Grupo 01',
            'moodle_role'       => 'student',
            'first_response_at' => (clone $t1Time)->addMinutes(25),
            'resolved_at'       => null,
            'created_at'        => $t1Time,
            'updated_at'        => now()->subHours(4),
        ]);

        \App\Models\TicketHistory::create([
            'ticket_id'   => $ticket1->id,
            'changed_by'  => $student->id,
            'old_status'  => null,
            'new_status'  => 'open',
            'comments'    => 'Ticket registrado por el estudiante desde el aula virtual Moodle.',
            'created_at'  => (clone $t1Time),
        ]);

        \App\Models\TicketHistory::create([
            'ticket_id'   => $ticket1->id,
            'changed_by'  => $agent1->id,
            'old_status'  => 'open',
            'new_status'  => 'in_progress',
            'comments'    => "Hola estimado estudiante, Juan por aquí. He tomado tu caso prioritario. He verificado que la cuota máxima por defecto de la actividad en Moodle estaba fijada en 10MB.",
            'created_at'  => (clone $t1Time)->addMinutes(25),
        ]);

        \App\Models\TicketHistory::create([
            'ticket_id'   => $ticket1->id,
            'changed_by'  => $agent1->id,
            'old_status'  => 'in_progress',
            'new_status'  => 'waiting',
            'comments'    => "Acabamos de incrementar el parámetro 'upload_max_filesize' y el límite de la tarea en Moodle a 50MB. ¿Podrías intentar subirlo nuevamente en una ventana de incógnito y confirmarnos si te permite completar la entrega?",
            'created_at'  => (clone $t1Time)->addMinutes(35),
        ]);

        \App\Models\TicketHistory::create([
            'ticket_id'   => $ticket1->id,
            'changed_by'  => $student->id,
            'old_status'  => 'waiting',
            'new_status'  => 'in_progress',
            'comments'    => "Hola Juan, intenté subir el ZIP y ahora carga hasta el 80%, pero luego se queda en bucle de subida y me dice 'Error de red al comunicarse con el servidor'. ¿Puede ser por la conexión o el servidor?",
            'created_at'  => (clone $t1Time)->addHours(4),
        ]);

        \App\Models\TicketHistory::create([
            'ticket_id'   => $ticket1->id,
            'changed_by'  => $agent1->id,
            'old_status'  => 'in_progress',
            'new_status'  => 'waiting',
            'comments'    => "Hemos aumentado el timeout de subida en Nginx (proxy inverso) de 60s a 300s. Por favor vuelve a probar el envío. Mantendremos el ticket abierto y en espera de tu confirmación.",
            'created_at'  => now()->subHours(4),
        ]);

        // -------------------------------------------------------------------------
        // CASO 2: Cuenta bloqueada por intentos fallidos de examen (Estado actual: Resuelto)
        // -------------------------------------------------------------------------
        $t2Time = now()->subDays(5);
        $ticket2 = \App\Models\Ticket::create([
            'title'             => 'Cuenta institucional bloqueada minutos antes del Examen Parcial',
            'description'       => 'Se me bloqueó el acceso con mensaje "Demasiados intentos fallidos". Tengo el examen de Cálculo Numérico en 30 minutos y el sistema no me deja restablecer.',
            'status'            => 'resolved',
            'priority'          => 'critical',
            'category_id'       => $catModels['Acceso y Login']->id,
            'assigned_to'       => $agent2->id,
            'created_by'        => $student->id,
            'moodle_user_id'    => 'moodle_std_2091',
            'moodle_course'     => 'Cálculo Numérico - Semestre 2',
            'moodle_role'       => 'student',
            'first_response_at' => (clone $t2Time)->addMinutes(12),
            'resolved_at'       => (clone $t2Time)->addHours(2),
            'created_at'        => $t2Time,
            'updated_at'        => (clone $t2Time)->addHours(2),
        ]);

        \App\Models\TicketHistory::create([
            'ticket_id'   => $ticket2->id,
            'changed_by'  => $student->id,
            'old_status'  => null,
            'new_status'  => 'open',
            'comments'    => 'Apertura de caso urgente por bloqueo de credenciales LDAP.',
            'created_at'  => (clone $t2Time),
        ]);

        \App\Models\TicketHistory::create([
            'ticket_id'   => $ticket2->id,
            'changed_by'  => $agent2->id,
            'old_status'  => 'open',
            'new_status'  => 'in_progress',
            'comments'    => 'Hola, María del equipo de soporte atendiendo. Estamos revisando los registros de autenticación en el servidor central.',
            'created_at'  => (clone $t2Time)->addMinutes(12),
        ]);

        \App\Models\TicketHistory::create([
            'ticket_id'   => $ticket2->id,
            'changed_by'  => $agent2->id,
            'old_status'  => 'in_progress',
            'new_status'  => 'waiting',
            'comments'    => 'Desbloqueamos tu cuenta en el Directorio Activo y forzamos la sincronización de tokens con Moodle. Te enviamos una contraseña provisoria por SMS. Por favor ingresa y confirma acceso.',
            'created_at'  => (clone $t2Time)->addMinutes(25),
        ]);

        \App\Models\TicketHistory::create([
            'ticket_id'   => $ticket2->id,
            'changed_by'  => $student->id,
            'old_status'  => 'waiting',
            'new_status'  => 'in_progress',
            'comments'    => '¡Excelente María! Ya logré entrar y pude ingresar al aula del examen justo a tiempo. Muchísimas gracias.',
            'created_at'  => (clone $t2Time)->addHours(1)->addMinutes(40),
        ]);

        \App\Models\TicketHistory::create([
            'ticket_id'   => $ticket2->id,
            'changed_by'  => $agent2->id,
            'old_status'  => 'in_progress',
            'new_status'  => 'resolved',
            'comments'    => 'Confirmación de acceso recibida satisfactoriamente. Incidencia resuelta con éxito en tiempo récord.',
            'created_at'  => (clone $t2Time)->addHours(2),
        ]);

        // -------------------------------------------------------------------------
        // CASO 3: Calificación y rúbrica no visible en Libro de Calificaciones (Estado actual: En Proceso)
        // -------------------------------------------------------------------------
        $t3Time = now()->subDays(2);
        $ticket3 = \App\Models\Ticket::create([
            'title'             => 'Discrepancia en calificación: la nota del Hito 2 no se refleja en el total del curso',
            'description'       => 'El docente me colocó 95/100 en la rúbrica de la tarea Hito 2, pero en el Libro de Calificaciones mi ponderación sigue en 0% y no suma al total de la materia.',
            'status'            => 'in_progress',
            'priority'          => 'medium',
            'category_id'       => $catModels['Calificaciones']->id,
            'assigned_to'       => $agent1->id,
            'created_by'        => $student->id,
            'moodle_user_id'    => 'moodle_std_1042',
            'moodle_course'     => 'Bases de Datos Avanzadas',
            'moodle_role'       => 'student',
            'first_response_at' => (clone $t3Time)->addHours(1),
            'resolved_at'       => null,
            'created_at'        => $t3Time,
            'updated_at'        => now()->subHours(2),
        ]);

        \App\Models\TicketHistory::create([
            'ticket_id'   => $ticket3->id,
            'changed_by'  => $student->id,
            'old_status'  => null,
            'new_status'  => 'open',
            'comments'    => 'Ticket iniciado con captura adjunta de la nota de rúbrica.',
            'created_at'  => (clone $t3Time),
        ]);

        \App\Models\TicketHistory::create([
            'ticket_id'   => $ticket3->id,
            'changed_by'  => $agent1->id,
            'old_status'  => 'open',
            'new_status'  => 'in_progress',
            'comments'    => 'Caso asignado a Juan. Accediendo al libro de calificaciones en modo de auditoría de cálculo ponderado.',
            'created_at'  => (clone $t3Time)->addHours(1),
        ]);

        \App\Models\TicketHistory::create([
            'ticket_id'   => $ticket3->id,
            'changed_by'  => $agent1->id,
            'old_status'  => 'in_progress',
            'new_status'  => 'waiting',
            'comments'    => 'Encontramos que la categoría "Hitos Prácticos" tenía marcada la opción "Excluir calificaciones vacías" y la fórmula matemática tenía un identificador desactualizado. Notificamos al profesor para validar la fórmula antes de recalcular.',
            'created_at'  => (clone $t3Time)->addHours(2),
        ]);

        \App\Models\TicketHistory::create([
            'ticket_id'   => $ticket3->id,
            'changed_by'  => $teacher->id,
            'old_status'  => 'waiting',
            'new_status'  => 'in_progress',
            'comments'    => 'Docente responde: Estimado soporte, autorizo la corrección de la fórmula de agregación. Por favor ejecuten el recálculo masivo para todos los estudiantes de la sección.',
            'created_at'  => now()->subHours(6),
        ]);

        \App\Models\TicketHistory::create([
            'ticket_id'   => $ticket3->id,
            'changed_by'  => $agent1->id,
            'old_status'  => 'in_progress',
            'new_status'  => 'in_progress',
            'comments'    => 'Ejecutando script de actualización de notas en background para la cohorte completa. En espera de finalización del cron de Moodle.',
            'created_at'  => now()->subHours(2),
        ]);

        // -------------------------------------------------------------------------
        // CASO 4: Paquete interactivo SCORM con pantalla en blanco en iPad (Estado actual: Esperando Respuesta)
        // -------------------------------------------------------------------------
        $t4Time = now()->subDays(4);
        $ticket4 = \App\Models\Ticket::create([
            'title'             => 'Objetos de aprendizaje interactivos (SCORM) no cargan en dispositivos Apple / Safari',
            'description'       => 'Varios estudiantes del curso Física Aplicada reportan que al hacer clic en los laboratorios interactivos del Módulo 3 la ventana queda totalmente en blanco en iPad y Mac.',
            'status'            => 'waiting',
            'priority'          => 'high',
            'category_id'       => $catModels['Error en Contenido']->id,
            'assigned_to'       => $agent2->id,
            'created_by'        => $teacher->id,
            'moodle_user_id'    => 'moodle_prof_88',
            'moodle_course'     => 'Física Aplicada I',
            'moodle_role'       => 'teacher',
            'first_response_at' => (clone $t4Time)->addHours(2),
            'resolved_at'       => null,
            'created_at'        => $t4Time,
            'updated_at'        => now()->subHours(8),
        ]);

        \App\Models\TicketHistory::create([
            'ticket_id'   => $ticket4->id,
            'changed_by'  => $teacher->id,
            'old_status'  => null,
            'new_status'  => 'open',
            'comments'    => 'Docente solicita asistencia técnica por incompatibilidad de recursos SCORM 1.2.',
            'created_at'  => (clone $t4Time),
        ]);

        \App\Models\TicketHistory::create([
            'ticket_id'   => $ticket4->id,
            'changed_by'  => $agent2->id,
            'old_status'  => 'open',
            'new_status'  => 'in_progress',
            'comments'    => 'Soporte María asume el ticket. Diagnosticando compatibilidad de políticas SameSite en cookies de Safari e iframes.',
            'created_at'  => (clone $t4Time)->addHours(2),
        ]);

        \App\Models\TicketHistory::create([
            'ticket_id'   => $ticket4->id,
            'changed_by'  => $agent2->id,
            'old_status'  => 'in_progress',
            'new_status'  => 'waiting',
            'comments'    => 'Profesor Carlos, hemos reempaquetado el SCORM y configurado la apertura forzada en nueva ventana (pop-up) para evitar el bloqueo de cross-origin de Safari. ¿Podría pedirle a 1 o 2 alumnos que prueben y nos informen?',
            'created_at'  => (clone $t4Time)->addHours(5),
        ]);

        \App\Models\TicketHistory::create([
            'ticket_id'   => $ticket4->id,
            'changed_by'  => $teacher->id,
            'old_status'  => 'waiting',
            'new_status'  => 'in_progress',
            'comments'    => 'Hola María, un alumno probó y ahora carga el menú principal, pero no guarda el puntaje del test al cerrar la ventana.',
            'created_at'  => now()->subDays(1),
        ]);

        \App\Models\TicketHistory::create([
            'ticket_id'   => $ticket4->id,
            'changed_by'  => $agent2->id,
            'old_status'  => 'in_progress',
            'new_status'  => 'waiting',
            'comments'    => 'Ajustamos la función LMSCommit() en el manifiesto JavaScript para sincronizar el puntaje antes del evento onbeforeunload. Por favor indiquen si ahora el libro de calificaciones registra el intento.',
            'created_at'  => now()->subHours(8),
        ]);

        // -------------------------------------------------------------------------
        // CASO 5: Solicitud de cambio de comisión por cruce horario (Estado actual: Cerrado)
        // -------------------------------------------------------------------------
        $t5Time = now()->subDays(7);
        $ticket5 = \App\Models\Ticket::create([
            'title'             => 'Solicitud formal de cambio de paralelo en Redes de Computadoras por motivos laborales',
            'description'       => 'Solicito traspaso del paralelo matutino al paralelo nocturno. Se adjunta carta de trabajo y horario de actividades.',
            'status'            => 'closed',
            'priority'          => 'low',
            'category_id'       => $catModels['Consulta General']->id,
            'assigned_to'       => $agent1->id,
            'created_by'        => $student->id,
            'moodle_user_id'    => 'moodle_std_1042',
            'moodle_course'     => 'Redes de Computadoras',
            'moodle_role'       => 'student',
            'first_response_at' => (clone $t5Time)->addHours(3),
            'resolved_at'       => (clone $t5Time)->addDays(2),
            'created_at'        => $t5Time,
            'updated_at'        => (clone $t5Time)->addDays(3),
        ]);

        \App\Models\TicketHistory::create([
            'ticket_id'   => $ticket5->id,
            'changed_by'  => $student->id,
            'old_status'  => null,
            'new_status'  => 'open',
            'comments'    => 'Solicitud ingresada con documentación digital de respaldo.',
            'created_at'  => (clone $t5Time),
        ]);

        \App\Models\TicketHistory::create([
            'ticket_id'   => $ticket5->id,
            'changed_by'  => $agent1->id,
            'old_status'  => 'open',
            'new_status'  => 'in_progress',
            'comments'    => 'Buen día. Procedo a verificar disponibilidad de plazas en el grupo nocturno con Secretaría Académica.',
            'created_at'  => (clone $t5Time)->addHours(3),
        ]);

        \App\Models\TicketHistory::create([
            'ticket_id'   => $ticket5->id,
            'changed_by'  => $agent1->id,
            'old_status'  => 'in_progress',
            'new_status'  => 'waiting',
            'comments'    => 'Existen 2 vacantes disponibles. Para formalizar el traspaso en el sistema administrativo, requerimos tu número de matrícula y firma digital del formulario FO-04.',
            'created_at'  => (clone $t5Time)->addHours(6),
        ]);

        \App\Models\TicketHistory::create([
            'ticket_id'   => $ticket5->id,
            'changed_by'  => $student->id,
            'old_status'  => 'waiting',
            'new_status'  => 'in_progress',
            'comments'    => 'Adjunto formulario FO-04 firmado digitalmente. Matrícula: 2024-TI-0982.',
            'created_at'  => (clone $t5Time)->addDays(1),
        ]);

        \App\Models\TicketHistory::create([
            'ticket_id'   => $ticket5->id,
            'changed_by'  => $agent1->id,
            'old_status'  => 'in_progress',
            'new_status'  => 'resolved',
            'comments'    => 'Traspaso completado con éxito. Ya figuras matriculado en el Grupo Nocturno de Moodle y en el registro académico oficial.',
            'created_at'  => (clone $t5Time)->addDays(2),
        ]);

        \App\Models\TicketHistory::create([
            'ticket_id'   => $ticket5->id,
            'changed_by'  => $admin->id,
            'old_status'  => 'resolved',
            'new_status'  => 'closed',
            'comments'    => 'Ticket cerrado automáticamente tras cumplirse el período de 24 horas sin objeciones por parte del solicitante.',
            'created_at'  => (clone $t5Time)->addDays(3),
        ]);

        // =========================================================================
        // TICKETS ADICIONALES DE RELLENO PARA MANTENER VOLUMEN Y MÉTRICAS
        // =========================================================================
        $statuses   = ['open', 'in_progress', 'waiting', 'resolved', 'closed'];
        $priorities = ['low', 'medium', 'high', 'critical'];
        $moodleRoles = ['student', 'teacher', 'course_creator'];

        for ($i = 1; $i <= 20; $i++) {
            $status   = $statuses[array_rand($statuses)];
            $priority = $priorities[array_rand($priorities)];

            $ticket = \App\Models\Ticket::create([
                'title'           => "Incidencia de prueba #{$i}",
                'description'     => "Descripción detallada del problema número {$i} reportado desde Moodle.",
                'status'          => $status,
                'priority'        => $priority,
                'category_id'     => $catIds[array_rand($catIds)],
                'assigned_to'     => [$agent1->id, $agent2->id][array_rand([0, 1])],
                'created_by'      => $student->id,
                'moodle_user_id'  => 'moodle_user_' . rand(100, 999),
                'moodle_course'   => 'Curso Demo ' . rand(1, 5),
                'moodle_role'     => $moodleRoles[array_rand($moodleRoles)],
                'first_response_at' => now()->subHours(rand(1, 10)),
                'resolved_at'     => in_array($status, ['resolved', 'closed']) ? now()->subHours(rand(1, 5)) : null,
                'created_at'      => now()->subDays(rand(1, 60)),
                'updated_at'      => now()->subDays(rand(0, 5)),
            ]);

            \App\Models\TicketHistory::create([
                'ticket_id'  => $ticket->id,
                'changed_by' => $admin->id,
                'old_status' => null,
                'new_status' => 'open',
                'comments'   => 'Ticket creado y asignado automáticamente.',
            ]);
        }

        // --- Interacciones del Bot ---
        $intents = ['password_reset', 'course_access', 'grade_query', 'general_info', 'technical_error'];
        for ($i = 1; $i <= 15; $i++) {
            \App\Models\BotInteraction::create([
                'user_id'                 => $student->id,
                'intent'                  => $intents[array_rand($intents)],
                'query'                   => "¿Cómo puedo {$intents[array_rand($intents)]} en la plataforma?",
                'response'                => 'El bot respondió automáticamente con los pasos indicados.',
                'resolved'                => (bool) rand(0, 1),
                'resolution_time_seconds' => rand(5, 120),
                'created_at'              => now()->subDays(rand(1, 30)),
            ]);
        }
    }
}
