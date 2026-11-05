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

        // --- Categorías ---
        $categories = [
            ['name' => 'Acceso y Login',      'description' => 'Problemas para ingresar a la plataforma'],
            ['name' => 'Error en Contenido',  'description' => 'Contenido incorrecto o faltante en cursos'],
            ['name' => 'Calificaciones',      'description' => 'Dudas o errores en notas y evaluaciones'],
            ['name' => 'Problemas Técnicos',  'description' => 'Fallas técnicas generales del sistema'],
            ['name' => 'Consulta General',    'description' => 'Preguntas generales de uso de la plataforma'],
        ];

        foreach ($categories as $cat) {
            \App\Models\TicketCategory::create($cat);
        }

        $catIds = \App\Models\TicketCategory::pluck('id')->toArray();

        // --- Tickets de prueba ---
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
