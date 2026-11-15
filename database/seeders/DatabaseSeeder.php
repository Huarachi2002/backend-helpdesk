<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Rol;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\TicketPrioridad;
use App\Models\User;
use App\Models\BotInteraction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with the redefined structure.
     */
    public function run()
    {
        // =========================================================================
        // 1. ROLES
        // =========================================================================
        $roles = [
            'DIRECTOR'   => Rol::create([
                'nombre'      => 'DIRECTOR',
                'descripcion' => 'Director o Administrador general de la plataforma de soporte.',
                'es_interno'  => true,
            ]),
            'TECNICO'    => Rol::create([
                'nombre'      => 'TECNICO',
                'descripcion' => 'Personal técnico de soporte encargado de resolver incidencias.',
                'es_interno'  => true,
            ]),
            'DOCENTE'    => Rol::create([
                'nombre'      => 'DOCENTE',
                'descripcion' => 'Profesor o facilitador del campus virtual Moodle.',
                'es_interno'  => false,
            ]),
            'ESTUDIANTE' => Rol::create([
                'nombre'      => 'ESTUDIANTE',
                'descripcion' => 'Alumno matriculado en cursos del campus virtual.',
                'es_interno'  => false,
            ]),
            'VISITANTE'  => Rol::create([
                'nombre'      => 'VISITANTE',
                'descripcion' => 'Usuario anónimo o externo a la institución.',
                'es_interno'  => false,
            ]),
        ];

        // =========================================================================
        // 2. CATEGORÍAS
        // =========================================================================
        $categoriesData = [
            ['nombre' => 'Acceso y Login',     'descripcion' => 'Problemas para ingresar a la plataforma y credenciales.'],
            ['nombre' => 'Error en Contenido', 'descripcion' => 'Contenido incorrecto o enlaces caídos en cursos.'],
            ['nombre' => 'Calificaciones',     'descripcion' => 'Dudas o inconsistencias en notas y libros de calificaciones.'],
            ['nombre' => 'Problemas Técnicos', 'descripcion' => 'Fallas de conectividad, carga de archivos o servidores.'],
            ['nombre' => 'Consulta General',   'descripcion' => 'Dudas de navegación y uso del campus virtual.'],
        ];

        $categorias = [];
        foreach ($categoriesData as $cat) {
            $categorias[$cat['nombre']] = Categoria::create([
                'nombre'      => $cat['nombre'],
                'descripcion' => $cat['descripcion'],
                'activo'      => true,
            ]);
        }
        $allCatIds = array_map(fn($c) => $c->id, $categorias);

        // =========================================================================
        // 3. PRIORIDADES DE TICKET
        // =========================================================================
        $prioridades = [
            'low' => TicketPrioridad::create([
                'nombre'               => 'Baja',
                'codigo'               => 'low',
                'peso_nivel'           => 1,
                'sla_horas_resolucion' => 72,
                'activo'               => true,
            ]),
            'medium' => TicketPrioridad::create([
                'nombre'               => 'Media',
                'codigo'               => 'medium',
                'peso_nivel'           => 2,
                'sla_horas_resolucion' => 48,
                'activo'               => true,
            ]),
            'high' => TicketPrioridad::create([
                'nombre'               => 'Alta',
                'codigo'               => 'high',
                'peso_nivel'           => 3,
                'sla_horas_resolucion' => 24,
                'activo'               => true,
            ]),
            'critical' => TicketPrioridad::create([
                'nombre'               => 'Crítica',
                'codigo'               => 'critical',
                'peso_nivel'           => 4,
                'sla_horas_resolucion' => 4,
                'activo'               => true,
            ]),
        ];

        // =========================================================================
        // 4. USUARIOS
        // =========================================================================
        $admin = User::create([
            'rol_id'         => $roles['DIRECTOR']->id,
            'nombre'         => 'Admin',
            'apellido'       => 'Helpdesk',
            'correo'         => 'admin@helpdesk.com',
            'contrasenia'    => Hash::make('password'),
            'sesion'         => true,
            'moodle_user_id' => 1,
            'activo'         => true,
        ]);

        $juan = User::create([
            'rol_id'         => $roles['TECNICO']->id,
            'nombre'         => 'Juan',
            'apellido'       => 'Soporte',
            'correo'         => 'juan@helpdesk.com',
            'contrasenia'    => Hash::make('password'),
            'sesion'         => true,
            'moodle_user_id' => 2,
            'activo'         => true,
        ]);

        $maria = User::create([
            'rol_id'         => $roles['TECNICO']->id,
            'nombre'         => 'María',
            'apellido'       => 'Soporte',
            'correo'         => 'maria@helpdesk.com',
            'contrasenia'    => Hash::make('password'),
            'sesion'         => true,
            'moodle_user_id' => 3,
            'activo'         => true,
        ]);

        $student = User::create([
            'rol_id'         => $roles['ESTUDIANTE']->id,
            'nombre'         => 'Estudiante',
            'apellido'       => 'Demo',
            'correo'         => 'estudiante@moodle.com',
            'contrasenia'    => Hash::make('password'),
            'sesion'         => false,
            'moodle_user_id' => 1042,
            'activo'         => true,
        ]);

        $teacher = User::create([
            'rol_id'         => $roles['DOCENTE']->id,
            'nombre'         => 'Carlos',
            'apellido'       => 'Méndez',
            'correo'         => 'carlos.mendez@moodle.com',
            'contrasenia'    => Hash::make('password'),
            'sesion'         => false,
            'moodle_user_id' => 2055,
            'activo'         => true,
        ]);

        // =========================================================================
        // 5. ASIGNACIONES DE CATEGORÍAS Y PRIORIDADES A USUARIOS TÉCNICOS
        // =========================================================================
        // Admin: atiende todas las categorías y prioridades
        $admin->categorias()->sync($allCatIds);
        $admin->prioridades()->sync(array_map(fn($p) => $p->id, $prioridades));

        // Juan: Problemas Técnicos, Acceso y Login, Consulta General (prioridades Media, Alta, Crítica)
        $juan->categorias()->sync([
            $categorias['Problemas Técnicos']->id,
            $categorias['Acceso y Login']->id,
            $categorias['Consulta General']->id,
        ]);
        $juan->prioridades()->sync([
            $prioridades['medium']->id,
            $prioridades['high']->id,
            $prioridades['critical']->id,
        ]);

        // María: Error en Contenido, Calificaciones, Acceso y Login (prioridades Baja, Media, Alta)
        $maria->categorias()->sync([
            $categorias['Error en Contenido']->id,
            $categorias['Calificaciones']->id,
            $categorias['Acceso y Login']->id,
        ]);
        $maria->prioridades()->sync([
            $prioridades['low']->id,
            $prioridades['medium']->id,
            $prioridades['high']->id,
        ]);

        // =========================================================================
        // 6. 5 TICKETS REALES CON HISTORIAL DE CONVERSACIÓN LARGA
        // =========================================================================

        // --- CASO 1: Error 413 al subir entrega final (Esperando por Cliente) ---
        $t1Time = now()->subDays(3);
        $ticket1 = Ticket::create([
            'codigo'              => 'TCK-101',
            'asunto'              => 'Error 413 al subir proyecto final en Moodle (Límite excedido)',
            'descripcion'         => 'Buenas tardes equipo de soporte, intento cargar mi archivo ZIP de 18MB con el proyecto final de la materia Ingeniería Web II y la plataforma me arroja "413 Request Entity Too Large". La fecha de cierre es hoy a las 23:59.',
            'estado'              => 'ESPERANDO_POR_CLIENTE',
            'prioridad_id'        => $prioridades['critical']->id,
            'solicitante_id'      => $student->id,
            'tecnico_asignado_id' => $juan->id,
            'correo_hilo'         => 'msg-101-init@moodle.edu',
            'fecha_creacion'      => $t1Time,
            'fecha_respuesta'     => (clone $t1Time)->addMinutes(25),
            'fecha_resuelto'      => null,
            'fecha_actualizacion' => now()->subHours(4),
        ]);
        $ticket1->categorias()->attach($categorias['Problemas Técnicos']->id);

        TicketHistory::create([
            'ticket_id'   => $ticket1->id,
            'changed_by'  => $student->id,
            'old_status'  => null,
            'new_status'  => 'open',
            'comments'    => 'Ticket registrado por el estudiante desde el aula virtual Moodle.',
            'created_at'  => (clone $t1Time),
        ]);

        TicketHistory::create([
            'ticket_id'   => $ticket1->id,
            'changed_by'  => $juan->id,
            'old_status'  => 'open',
            'new_status'  => 'in_progress',
            'comments'    => "Hola estimado estudiante, Juan por aquí. He tomado tu caso prioritario. He verificado que la cuota máxima por defecto de la actividad en Moodle estaba fijada en 10MB.",
            'created_at'  => (clone $t1Time)->addMinutes(25),
        ]);

        TicketHistory::create([
            'ticket_id'   => $ticket1->id,
            'changed_by'  => $juan->id,
            'old_status'  => 'in_progress',
            'new_status'  => 'waiting',
            'comments'    => "Acabo de actualizar la cuota de subida en la tarea a 50MB en el servidor Nginx y php.ini. Por favor recarga el navegador e intenta subir el archivo de nuevo.",
            'created_at'  => (clone $t1Time)->addHours(1),
        ]);

        // --- CASO 2: Error en ponderación de examen parcial (Resuelto) ---
        $t2Time = now()->subDays(5);
        $ticket2 = Ticket::create([
            'codigo'              => 'TCK-102',
            'asunto'              => 'Inconsistencia en cálculo de ponderación de examen parcial en Libro de Calificaciones',
            'descripcion'         => 'Estimados, en el curso Base de Datos I grupo 02, la categoría "Exámenes Parciales" tiene configurada una ponderación del 30%, pero al agregar el Examen Parcial 2 el sistema lo está calculando sobre el 100% total sin aplicar el peso ponderado.',
            'estado'              => 'RESUELTO',
            'prioridad_id'        => $prioridades['high']->id,
            'solicitante_id'      => $teacher->id,
            'tecnico_asignado_id' => $maria->id,
            'correo_hilo'         => 'msg-102-init@moodle.edu',
            'fecha_creacion'      => $t2Time,
            'fecha_respuesta'     => (clone $t2Time)->addMinutes(45),
            'fecha_resuelto'      => (clone $t2Time)->addHours(8),
            'fecha_actualizacion' => (clone $t2Time)->addHours(8),
        ]);
        $ticket2->categorias()->attach($categorias['Calificaciones']->id);

        TicketHistory::create([
            'ticket_id'   => $ticket2->id,
            'changed_by'  => $teacher->id,
            'old_status'  => null,
            'new_status'  => 'open',
            'comments'    => 'Ticket aperturado por el docente Carlos Méndez.',
            'created_at'  => (clone $t2Time),
        ]);

        TicketHistory::create([
            'ticket_id'   => $ticket2->id,
            'changed_by'  => $maria->id,
            'old_status'  => 'open',
            'new_status'  => 'in_progress',
            'comments'    => 'Buen día Prof. Méndez. Estoy revisando la estructura del libro de calificaciones de su curso.',
            'created_at'  => (clone $t2Time)->addMinutes(45),
        ]);

        TicketHistory::create([
            'ticket_id'   => $ticket2->id,
            'changed_by'  => $maria->id,
            'old_status'  => 'in_progress',
            'new_status'  => 'resolved',
            'comments'    => 'Se ajustó la agregación de calificaciones a "Media ponderada simple de calificaciones". Los cálculos han sido recalculados correctamente para todos los alumnos.',
            'created_at'  => (clone $t2Time)->addHours(8),
        ]);

        // --- CASO 3: Cuenta bloqueada por intentos fallidos (Cerrado) ---
        $t3Time = now()->subDays(7);
        $ticket3 = Ticket::create([
            'codigo'              => 'TCK-103',
            'asunto'              => 'Cuenta institucional bloqueada tras intentos fallidos de contraseña',
            'descripcion'         => 'No puedo ingresar a mi sesión de Moodle. Me dice "Su cuenta ha sido bloqueada temporalmente por exceso de intentos fallidos". Requiero desbloqueo urgente.',
            'estado'              => 'CERRADO',
            'prioridad_id'        => $prioridades['medium']->id,
            'solicitante_id'      => $student->id,
            'tecnico_asignado_id' => $juan->id,
            'correo_hilo'         => 'msg-103-init@moodle.edu',
            'fecha_creacion'      => $t3Time,
            'fecha_respuesta'     => (clone $t3Time)->addMinutes(15),
            'fecha_resuelto'      => (clone $t3Time)->addHours(2),
            'fecha_cierre'        => (clone $t3Time)->addDays(1),
            'fecha_actualizacion' => (clone $t3Time)->addDays(1),
        ]);
        $ticket3->categorias()->attach($categorias['Acceso y Login']->id);

        TicketHistory::create([
            'ticket_id'   => $ticket3->id,
            'changed_by'  => $student->id,
            'old_status'  => null,
            'new_status'  => 'open',
            'comments'    => 'Solicitud de desbloqueo ingresada.',
            'created_at'  => (clone $t3Time),
        ]);

        TicketHistory::create([
            'ticket_id'   => $ticket3->id,
            'changed_by'  => $juan->id,
            'old_status'  => 'open',
            'new_status'  => 'in_progress',
            'comments'    => 'Se verificó la identidad del estudiante en el directorio activo.',
            'created_at'  => (clone $t3Time)->addMinutes(15),
        ]);

        TicketHistory::create([
            'ticket_id'   => $ticket3->id,
            'changed_by'  => $juan->id,
            'old_status'  => 'in_progress',
            'new_status'  => 'resolved',
            'comments'    => 'Se desbloqueó la cuenta y se envió un enlace de restablecimiento al correo institucional.',
            'created_at'  => (clone $t3Time)->addHours(2),
        ]);

        TicketHistory::create([
            'ticket_id'   => $ticket3->id,
            'changed_by'  => $admin->id,
            'old_status'  => 'resolved',
            'new_status'  => 'closed',
            'comments'    => 'Caso cerrado satisfactoriamente.',
            'created_at'  => (clone $t3Time)->addDays(1),
        ]);

        // --- CASO 4: Videos incrustados no reproducen en Safari (En Proceso) ---
        $t4Time = now()->subDays(1);
        $ticket4 = Ticket::create([
            'codigo'              => 'TCK-104',
            'asunto'              => 'Videos incrustados vía iframe de Vimeo no se reproducen en navegadores Safari',
            'descripcion'         => 'Varios estudiantes del curso de Física I reportan que los videos explicativos en formato HLS/Vimeo no cargan en iPads y Macbooks.',
            'estado'              => 'CLIENTE_ESPERANDO_RESPUESTA',
            'prioridad_id'        => $prioridades['high']->id,
            'solicitante_id'      => $teacher->id,
            'tecnico_asignado_id' => $maria->id,
            'correo_hilo'         => 'msg-104-init@moodle.edu',
            'fecha_creacion'      => $t4Time,
            'fecha_respuesta'     => (clone $t4Time)->addHours(1),
            'fecha_resuelto'      => null,
            'fecha_actualizacion' => now()->subMinutes(30),
        ]);
        $ticket4->categorias()->attach($categorias['Error en Contenido']->id);

        TicketHistory::create([
            'ticket_id'   => $ticket4->id,
            'changed_by'  => $teacher->id,
            'old_status'  => null,
            'new_status'  => 'open',
            'comments'    => 'Reporte presentado con lista de estudiantes afectados.',
            'created_at'  => (clone $t4Time),
        ]);

        TicketHistory::create([
            'ticket_id'   => $ticket4->id,
            'changed_by'  => $maria->id,
            'old_status'  => 'open',
            'new_status'  => 'in_progress',
            'comments'    => 'Identificado problema de certificados de seguridad y cabeceras iframe SameSite en Safari.',
            'created_at'  => (clone $t4Time)->addHours(1),
        ]);

        // --- CASO 5: Solicitud de cambio de comisión o grupo (En Espera) ---
        $t5Time = now()->subHours(5);
        $ticket5 = Ticket::create([
            'codigo'              => 'TCK-105',
            'asunto'              => 'Solicitud de cambio de comisión por incompatibilidad de horario laboral',
            'descripcion'         => 'Presento formalmente mi constancia laboral para solicitar el traspaso de comisión del Grupo 01 (mañana) al Grupo 03 (noche) de Cálculo II.',
            'estado'              => 'EN_ESPERA',
            'prioridad_id'        => $prioridades['low']->id,
            'solicitante_id'      => $student->id,
            'tecnico_asignado_id' => $juan->id,
            'correo_hilo'         => 'msg-105-init@moodle.edu',
            'fecha_creacion'      => $t5Time,
            'fecha_respuesta'     => null,
            'fecha_resuelto'      => null,
            'fecha_actualizacion' => $t5Time,
        ]);
        $ticket5->categorias()->attach($categorias['Consulta General']->id);

        TicketHistory::create([
            'ticket_id'   => $ticket5->id,
            'changed_by'  => $student->id,
            'old_status'  => null,
            'new_status'  => 'open',
            'comments'    => 'Solicitud ingresada en espera de revisión técnica.',
            'created_at'  => $t5Time,
        ]);

        // =========================================================================
        // 7. TICKETS ADICIONALES PARA VOLUMEN Y MÉTRICAS
        // =========================================================================
        $estadosList   = ['EN_ESPERA', 'CLIENTE_ESPERANDO_RESPUESTA', 'ESPERANDO_POR_CLIENTE', 'RESUELTO', 'CERRADO'];
        $prioridadKeys = ['low', 'medium', 'high', 'critical'];

        for ($i = 6; $i <= 25; $i++) {
            $stKey = $estadosList[array_rand($estadosList)];
            $prKey = $prioridadKeys[array_rand($prioridadKeys)];
            $assigned = [$juan->id, $maria->id][array_rand([0, 1])];
            $tDate = now()->subDays(rand(1, 45));

            $t = Ticket::create([
                'codigo'              => 'TCK-' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'asunto'              => "Incidencia técnica del campus virtual #{$i}",
                'descripcion'         => "Reporte generado para seguimiento de actividad académica y soporte al usuario #{$i}.",
                'estado'              => $stKey,
                'prioridad_id'        => $prioridades[$prKey]->id,
                'solicitante_id'      => $student->id,
                'tecnico_asignado_id' => $assigned,
                'correo_hilo'         => "msg-{$i}@moodle.edu",
                'fecha_creacion'      => $tDate,
                'fecha_respuesta'     => (clone $tDate)->addHours(rand(1, 8)),
                'fecha_resuelto'      => in_array($stKey, ['RESUELTO', 'CERRADO']) ? (clone $tDate)->addHours(rand(9, 36)) : null,
                'fecha_cierre'        => ($stKey === 'CERRADO') ? (clone $tDate)->addDays(rand(2, 5)) : null,
                'fecha_actualizacion' => now()->subHours(rand(1, 24)),
            ]);

            $catKeys = array_keys($categorias);
            $t->categorias()->attach($categorias[$catKeys[array_rand($catKeys)]]->id);

            TicketHistory::create([
                'ticket_id'  => $t->id,
                'changed_by' => $student->id,
                'old_status' => null,
                'new_status' => 'open',
                'comments'   => 'Ticket registrado y asignado.',
                'created_at' => $tDate,
            ]);
        }

        // =========================================================================
        // 8. INTERACCIONES DEL BOT
        // =========================================================================
        $intents = ['password_reset', 'course_access', 'grade_query', 'general_info', 'technical_error'];
        for ($i = 1; $i <= 15; $i++) {
            BotInteraction::create([
                'user_id'                 => $student->id,
                'intent'                  => $intents[array_rand($intents)],
                'query'                   => "¿Cómo puedo solucionar mi consulta sobre {$intents[array_rand($intents)]} en la plataforma?",
                'response'                => 'El asistente virtual ha analizado su consulta y proporcionado los pasos de resolución sugeridos.',
                'resolved'                => (bool) rand(0, 1),
                'resolution_time_seconds' => rand(5, 120),
                'created_at'              => now()->subDays(rand(1, 30)),
            ]);
        }
    }
}
