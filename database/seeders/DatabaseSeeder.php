<?php

namespace Database\Seeders;

use App\Models\Asignacion;
use App\Models\BotInteraction;
use App\Models\Categoria;
use App\Models\Rol;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\TicketPrioridad;
use App\Models\User;
use App\Models\UsuarioAsignacion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder maestro estructurado de acuerdo al estado actual de la base de datos:
 * - 5 Roles (DIRECTOR, TECNICO, DOCENTE, ESTUDIANTE, VISITANTE)
 * - 8 Cargos del organigrama DEDTE (vía CargoSeeder)
 * - 6 Categorías reales
 * - 4 Prioridades (PK: codigo)
 * - 10 Usuarios reales del equipo/campus (PK: ci) con sus cargos, categorías y prioridades
 * - 20 Configuraciones institucionales (PK: clave, vía ConfiguracionSeeder)
 * - Tickets de ejemplo con asignacion + usuario_asignacion + ticket_historial + interacciones_bot
 */
class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // =====================================================================
        // 1. ROLES (exactamente los 5 roles actuales de la BD)
        // =====================================================================
        $rolesData = [
            ['nombre' => 'DIRECTOR',   'descripcion' => 'Director o Administrador general de la plataforma de soporte.', 'es_interno' => true],
            ['nombre' => 'TECNICO',    'descripcion' => 'Personal técnico de soporte encargado de resolver incidencias.', 'es_interno' => true],
            ['nombre' => 'DOCENTE',    'descripcion' => 'Profesor o facilitador del campus virtual Moodle.',             'es_interno' => false],
            ['nombre' => 'ESTUDIANTE', 'descripcion' => 'Alumno matriculado en cursos del campus virtual.',              'es_interno' => false],
            ['nombre' => 'VISITANTE',  'descripcion' => 'Usuario anónimo o externo a la institución.',                   'es_interno' => false],
        ];

        $roles = [];
        foreach ($rolesData as $r) {
            $roles[$r['nombre']] = Rol::updateOrCreate(['nombre' => $r['nombre']], $r);
        }

        // =====================================================================
        // 2. CARGOS DEL ORGANIGRAMA DEDTE (8 cargos actuales)
        // =====================================================================
        $this->call(CargoSeeder::class);

        // =====================================================================
        // 3. CATEGORÍAS (6 categorías actuales)
        // =====================================================================
        $categoriesData = [
            ['nombre' => 'Acceso y Login',     'descripcion' => 'Problemas para ingresar a la plataforma y credenciales.'],
            ['nombre' => 'Error en Contenido', 'descripcion' => 'Contenido incorrecto o enlaces caídos en cursos.'],
            ['nombre' => 'Calificaciones',     'descripcion' => 'Dudas o inconsistencias en notas y libros de calificaciones.'],
            ['nombre' => 'Problemas Técnicos', 'descripcion' => 'Fallas de conectividad, carga de archivos o servidores.'],
            ['nombre' => 'Consulta General',   'descripcion' => 'Dudas de navegación y uso del campus virtual.'],
            ['nombre' => 'Tecnico Web',        'descripcion' => 'Cualquier inconveniente con la Web'],
        ];

        $categorias = [];
        foreach ($categoriesData as $cat) {
            $categorias[$cat['nombre']] = Categoria::updateOrCreate(
                ['nombre' => $cat['nombre']],
                ['descripcion' => $cat['descripcion'], 'activo' => true]
            );
        }

        // =====================================================================
        // 4. PRIORIDADES DE TICKET (PK natural: codigo)
        // =====================================================================
        $prioridadesData = [
            ['codigo' => 'low',      'nombre' => 'Baja',    'peso_nivel' => 1, 'sla_horas_resolucion' => 72, 'activo' => true],
            ['codigo' => 'medium',   'nombre' => 'Media',   'peso_nivel' => 2, 'sla_horas_resolucion' => 48, 'activo' => true],
            ['codigo' => 'high',     'nombre' => 'Alta',    'peso_nivel' => 3, 'sla_horas_resolucion' => 24, 'activo' => true],
            ['codigo' => 'critical', 'nombre' => 'Crítica', 'peso_nivel' => 4, 'sla_horas_resolucion' => 4,  'activo' => true],
        ];

        foreach ($prioridadesData as $p) {
            TicketPrioridad::updateOrCreate(['codigo' => $p['codigo']], $p);
        }

        // =====================================================================
        // 5. USUARIOS ACTUALES DE LA BD (PK natural: ci)
        // =====================================================================
        $usuariosData = [
            [
                'ci'                => '1000001',
                'rol'               => 'DIRECTOR',
                'cargo_codigo'      => 'DEDTE-JD-001',
                'nombre'            => 'Yeferson',
                'apellido'          => 'Huarachi',
                'correo'            => 'roberthuarachi27@gmail.com',
                'sesion'            => true,
                'moodle_usuario_id' => null,
                'categorias'        => ['Acceso y Login', 'Error en Contenido', 'Calificaciones', 'Problemas Técnicos', 'Consulta General'],
                'prioridades'       => ['low', 'medium', 'high', 'critical'],
            ],
            [
                'ci'                => '1000002',
                'rol'               => 'TECNICO',
                'cargo_codigo'      => 'DEDTE-ASA-004',
                'nombre'            => 'Erick',
                'apellido'          => 'Aricari',
                'correo'            => 'erickaricariTEST@gmail.com',
                'sesion'            => true,
                'moodle_usuario_id' => null,
                'categorias'        => ['Acceso y Login', 'Problemas Técnicos', 'Consulta General'],
                'prioridades'       => ['medium', 'high', 'critical'],
            ],
            [
                'ci'                => '1000003',
                'rol'               => 'TECNICO',
                'cargo_codigo'      => 'DEDTE-APV-007',
                'nombre'            => 'Ruperto',
                'apellido'          => 'Delgado',
                'correo'            => 'rupertodelgado05@gmail.com',
                'sesion'            => true,
                'moodle_usuario_id' => null,
                'categorias'        => ['Acceso y Login', 'Error en Contenido', 'Calificaciones'],
                'prioridades'       => ['low', 'medium', 'high'],
            ],
            [
                'ci'                => '1000004',
                'rol'               => 'ESTUDIANTE',
                'cargo_codigo'      => null,
                'nombre'            => 'Estudiante',
                'apellido'          => 'Gutierrez',
                'correo'            => 'yefhuarachi27@gmail.com',
                'sesion'            => false,
                'moodle_usuario_id' => 1042,
                'categorias'        => [],
                'prioridades'       => [],
            ],
            [
                'ci'                => '1000005',
                'rol'               => 'DOCENTE',
                'cargo_codigo'      => null,
                'nombre'            => 'Carlos',
                'apellido'          => 'Méndez',
                'correo'            => 'carlos.mendez@moodle.com',
                'sesion'            => false,
                'moodle_usuario_id' => 2055,
                'categorias'        => [],
                'prioridades'       => [],
            ],
            [
                'ci'                => '1000006',
                'rol'               => 'TECNICO',
                'cargo_codigo'      => 'DEDTE-RDI-003',
                'nombre'            => 'Juan Carlos',
                'apellido'          => 'Mamani Quispe',
                'correo'            => 'juan.mamani@gmail.com',
                'sesion'            => true,
                'moodle_usuario_id' => null,
                'categorias'        => ['Acceso y Login', 'Calificaciones', 'Consulta General'],
                'prioridades'       => ['low', 'medium', 'high', 'critical'],
            ],
            [
                'ci'                => '1000007',
                'rol'               => 'TECNICO',
                'cargo_codigo'      => 'DEDTE-TSE-008',
                'nombre'            => 'Carlos',
                'apellido'          => 'Paredes',
                'correo'            => 'carlos.paredes@gmail.com',
                'sesion'            => true,
                'moodle_usuario_id' => null,
                'categorias'        => ['Acceso y Login'],
                'prioridades'       => ['medium', 'high', 'critical'],
            ],
            [
                'ci'                => '1000008',
                'rol'               => 'DIRECTOR',
                'cargo_codigo'      => 'DEDTE-CAP-002',
                'nombre'            => 'Erick',
                'apellido'          => 'Aricari',
                'correo'            => 'erickaricari@gmail.com',
                'sesion'            => true,
                'moodle_usuario_id' => null,
                'categorias'        => ['Acceso y Login', 'Error en Contenido', 'Calificaciones', 'Problemas Técnicos', 'Consulta General', 'Tecnico Web'],
                'prioridades'       => ['medium', 'high', 'critical'],
            ],
            [
                'ci'                => '1000009',
                'rol'               => 'DIRECTOR',
                'cargo_codigo'      => 'DEDTE-CTE-006',
                'nombre'            => 'Matias',
                'apellido'          => 'Franca',
                'correo'            => 'diufram007@gmail.com',
                'sesion'            => true,
                'moodle_usuario_id' => null,
                'categorias'        => ['Acceso y Login', 'Error en Contenido', 'Calificaciones', 'Problemas Técnicos', 'Consulta General', 'Tecnico Web'],
                'prioridades'       => ['medium', 'high', 'critical'],
            ],
            [
                'ci'                => '1000010',
                'rol'               => 'TECNICO',
                'cargo_codigo'      => 'DEDTE-RCC-005',
                'nombre'            => 'Julio',
                'apellido'          => 'Iglesia',
                'correo'            => 'julio@gmail.com',
                'sesion'            => true,
                'moodle_usuario_id' => null,
                'categorias'        => ['Calificaciones', 'Consulta General', 'Tecnico Web'],
                'prioridades'       => ['high', 'critical'],
            ],
        ];

        $usuarios = [];
        foreach ($usuariosData as $u) {
            $user = User::updateOrCreate(
                ['ci' => $u['ci']],
                [
                    'rol_id'            => $roles[$u['rol']]->id,
                    'cargo_codigo'      => $u['cargo_codigo'],
                    'nombre'            => $u['nombre'],
                    'apellido'          => $u['apellido'],
                    'correo'            => $u['correo'],
                    'contrasenia'       => $u['sesion'] ? Hash::make('password') : null,
                    'sesion'            => $u['sesion'],
                    'moodle_usuario_id' => $u['moodle_usuario_id'],
                    'activo'            => true,
                ]
            );

            if (!empty($u['categorias'])) {
                $catIds = array_map(fn ($nombre) => $categorias[$nombre]->id, $u['categorias']);
                $user->categorias()->sync($catIds);
            }
            if (!empty($u['prioridades'])) {
                $user->prioridades()->sync($u['prioridades']);
            }

            $usuarios[$u['ci']] = $user;
        }

        // =====================================================================
        // 6. CONFIGURACIONES INSTITUCIONALES (PK natural: clave)
        // =====================================================================
        $this->call(ConfiguracionSeeder::class);

        // =====================================================================
        // 7. TICKETS BASE DE DEMO (solo si la tabla está vacía)
        // =====================================================================
        if (Ticket::count() === 0) {
            $student = $usuarios['1000004'];
            $teacher = $usuarios['1000005'];
            $tecA    = $usuarios['1000002'];
            $tecB    = $usuarios['1000003'];

            $t1Time = now()->subDays(3);
            $ticket1 = Ticket::create([
                'codigo'              => 'TCK-001',
                'asunto'              => 'Error 413 al subir proyecto final en Moodle (Límite excedido)',
                'descripcion'         => 'Intento cargar mi archivo ZIP de 18MB y la plataforma arroja "413 Request Entity Too Large".',
                'estado'              => 'ESPERANDO_POR_CLIENTE',
                'prioridad_codigo'    => 'critical',
                'solicitante_ci'      => $student->ci,
                'tecnico_asignado_ci' => $tecA->ci,
                'correo_hilo'         => 'msg-001-init@moodle.edu',
                'fecha_creacion'      => $t1Time,
                'fecha_respuesta'     => (clone $t1Time)->addMinutes(25),
                'fecha_actualizacion' => now()->subHours(4),
            ]);
            $ticket1->categorias()->attach($categorias['Problemas Técnicos']->id);

            $asig1 = Asignacion::create([
                'ticket_codigo'        => $ticket1->codigo,
                'estado'               => Asignacion::ESTADO_APROBADA,
                'tipo_origen'          => Asignacion::TIPO_AUTOMATICA,
                'motivo_solicitud'     => 'Asignación automática inicial.',
                'cargo_destino_codigo' => $tecA->cargo_codigo,
                'fecha_creacion'       => $t1Time,
                'fecha_resolucion'     => $t1Time,
            ]);
            $asig1->registrarParticipante($tecA->ci, UsuarioAsignacion::ROL_TECNICO_DESTINO, $t1Time);

            TicketHistory::create([
                'ticket_codigo'   => $ticket1->codigo,
                'usuario_ci'      => $student->ci,
                'estado_anterior' => null,
                'estado_nuevo'    => 'open',
                'comentarios'     => 'Ticket registrado por el estudiante desde el aula virtual Moodle.',
                'fecha_creacion'  => $t1Time,
            ]);

            $t2Time = now()->subDays(5);
            $ticket2 = Ticket::create([
                'codigo'              => 'TCK-002',
                'asunto'              => 'Inconsistencia en ponderación de examen parcial en Libro de Calificaciones',
                'descripcion'         => 'La categoría "Exámenes Parciales" calcula el parcial sobre el 100% sin aplicar el peso del 30%.',
                'estado'              => 'RESUELTO',
                'prioridad_codigo'    => 'high',
                'solicitante_ci'      => $teacher->ci,
                'tecnico_asignado_ci' => $tecB->ci,
                'correo_hilo'         => 'msg-002-init@moodle.edu',
                'fecha_creacion'      => $t2Time,
                'fecha_respuesta'     => (clone $t2Time)->addMinutes(45),
                'fecha_resuelto'      => (clone $t2Time)->addHours(8),
                'fecha_actualizacion' => (clone $t2Time)->addHours(8),
            ]);
            $ticket2->categorias()->attach($categorias['Calificaciones']->id);

            $asig2 = Asignacion::create([
                'ticket_codigo'        => $ticket2->codigo,
                'estado'               => Asignacion::ESTADO_APROBADA,
                'tipo_origen'          => Asignacion::TIPO_AUTOMATICA,
                'motivo_solicitud'     => 'Asignación automática inicial.',
                'cargo_destino_codigo' => $tecB->cargo_codigo,
                'fecha_creacion'       => $t2Time,
                'fecha_resolucion'     => $t2Time,
            ]);
            $asig2->registrarParticipante($tecB->ci, UsuarioAsignacion::ROL_TECNICO_DESTINO, $t2Time);

            TicketHistory::create([
                'ticket_codigo'   => $ticket2->codigo,
                'usuario_ci'      => $teacher->ci,
                'estado_anterior' => null,
                'estado_nuevo'    => 'open',
                'comentarios'     => 'Ticket aperturado por el docente Carlos Méndez.',
                'fecha_creacion'  => $t2Time,
            ]);

            BotInteraction::create([
                'usuario_ci'                 => $student->ci,
                'intencion'                  => 'reset_password',
                'consulta'                   => '¿Cómo restablezco mi contraseña de Moodle?',
                'respuesta'                  => 'Puedes restablecer tu contraseña desde la opción "¿Olvidó su contraseña?" con tu correo institucional.',
                'resuelto'                   => true,
                'tiempo_resolucion_segundos' => 12,
                'fecha_creacion'             => now()->subDays(2),
            ]);
        }
    }
}
