<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Refactorización integral de esquema:
 * 1. Nuevas claves primarias naturales (eliminando `id`):
 *    - usuarios: ci (VARCHAR(30))
 *    - ticket_prioridad: codigo (VARCHAR(20))
 *    - configuraciones: clave (VARCHAR(80))
 *    - ticket: codigo (VARCHAR(20))
 * 2. Actualización de todas las llaves foráneas dependientes + Sanctum (`personal_access_tokens.tokenable_id`).
 * 3. Traducción al español de todas las tablas y columnas de dominio:
 *    - tickets_history -> ticket_historial
 *    - bot_interactions -> interacciones_bot
 *    - bitacora_accesos (user_agent -> agente_usuario, created_at -> fecha_creacion)
 *    - ticket_mensaje (remitente_email -> remitente_correo, correo_message_id -> correo_mensaje_id)
 *    - usuarios (moodle_user_id -> moodle_usuario_id)
 */
class RefactorNaturalPksAndSpanishSchema extends Migration
{
    public function up()
    {
        // =====================================================================
        // 1. AGREGAR `ci` A `usuarios` Y POBLAR REGISTROS EXISTENTES
        // =====================================================================
        DB::statement("ALTER TABLE usuarios ADD COLUMN ci VARCHAR(30)");
        DB::statement("UPDATE usuarios SET ci = CAST(1000000 + id AS VARCHAR) WHERE ci IS NULL");
        DB::statement("ALTER TABLE usuarios ALTER COLUMN ci SET NOT NULL");
        DB::statement("ALTER TABLE usuarios ADD CONSTRAINT usuarios_ci_unique UNIQUE (ci)");

        // =====================================================================
        // 2. AGREGAR NUEVAS COLUMNAS FK EN TODAS LAS TABLAS DEPENDIENTES
        // =====================================================================
        // ticket
        DB::statement("ALTER TABLE ticket ADD COLUMN solicitante_ci VARCHAR(30)");
        DB::statement("ALTER TABLE ticket ADD COLUMN tecnico_asignado_ci VARCHAR(30)");
        DB::statement("ALTER TABLE ticket ADD COLUMN prioridad_codigo VARCHAR(20)");

        // asignacion
        DB::statement("ALTER TABLE asignacion ADD COLUMN ticket_codigo VARCHAR(20)");

        // usuario_asignacion
        DB::statement("ALTER TABLE usuario_asignacion ADD COLUMN usuario_ci VARCHAR(30)");

        // usuario_categoria
        DB::statement("ALTER TABLE usuario_categoria ADD COLUMN usuario_ci VARCHAR(30)");

        // usuario_prioridad
        DB::statement("ALTER TABLE usuario_prioridad ADD COLUMN usuario_ci VARCHAR(30)");
        DB::statement("ALTER TABLE usuario_prioridad ADD COLUMN prioridad_codigo VARCHAR(20)");

        // ticket_categoria
        DB::statement("ALTER TABLE ticket_categoria ADD COLUMN ticket_codigo VARCHAR(20)");

        // ticket_mensaje
        DB::statement("ALTER TABLE ticket_mensaje ADD COLUMN ticket_codigo VARCHAR(20)");
        DB::statement("ALTER TABLE ticket_mensaje ADD COLUMN usuario_ci VARCHAR(30)");

        // tickets_history
        DB::statement("ALTER TABLE tickets_history ADD COLUMN ticket_codigo VARCHAR(20)");
        DB::statement("ALTER TABLE tickets_history ADD COLUMN usuario_ci VARCHAR(30)");

        // notificaciones
        DB::statement("ALTER TABLE notificaciones ADD COLUMN usuario_ci VARCHAR(30)");
        DB::statement("ALTER TABLE notificaciones ADD COLUMN ticket_codigo VARCHAR(20)");

        // configuraciones
        DB::statement("ALTER TABLE configuraciones ADD COLUMN usuario_ci VARCHAR(30)");

        // bitacora_accesos
        DB::statement("ALTER TABLE bitacora_accesos ADD COLUMN usuario_ci VARCHAR(30)");

        // bot_interactions
        DB::statement("ALTER TABLE bot_interactions ADD COLUMN usuario_ci VARCHAR(30)");

        // =====================================================================
        // 3. POBLAR TODAS LAS NUEVAS FKs DESDE LOS `id` EXISTENTES
        // =====================================================================
        DB::statement("UPDATE ticket t SET solicitante_ci = u.ci FROM usuarios u WHERE t.solicitante_id = u.id");
        DB::statement("UPDATE ticket t SET tecnico_asignado_ci = u.ci FROM usuarios u WHERE t.tecnico_asignado_id = u.id");
        DB::statement("UPDATE ticket t SET prioridad_codigo = p.codigo FROM ticket_prioridad p WHERE t.prioridad_id = p.id");

        DB::statement("UPDATE asignacion a SET ticket_codigo = t.codigo FROM ticket t WHERE a.ticket_id = t.id");
        DB::statement("UPDATE usuario_asignacion ua SET usuario_ci = u.ci FROM usuarios u WHERE ua.usuario_id = u.id");
        DB::statement("UPDATE usuario_categoria uc SET usuario_ci = u.ci FROM usuarios u WHERE uc.usuario_id = u.id");

        DB::statement("UPDATE usuario_prioridad up SET usuario_ci = u.ci FROM usuarios u WHERE up.usuario_id = u.id");
        DB::statement("UPDATE usuario_prioridad up SET prioridad_codigo = p.codigo FROM ticket_prioridad p WHERE up.prioridad_id = p.id");

        DB::statement("UPDATE ticket_categoria tc SET ticket_codigo = t.codigo FROM ticket t WHERE tc.ticket_id = t.id");

        DB::statement("UPDATE ticket_mensaje tm SET ticket_codigo = t.codigo FROM ticket t WHERE tm.ticket_id = t.id");
        DB::statement("UPDATE ticket_mensaje tm SET usuario_ci = u.ci FROM usuarios u WHERE tm.usuario_id = u.id");

        DB::statement("UPDATE tickets_history th SET ticket_codigo = t.codigo FROM ticket t WHERE th.ticket_id = t.id");
        DB::statement("UPDATE tickets_history th SET usuario_ci = u.ci FROM usuarios u WHERE th.changed_by = u.id");

        DB::statement("UPDATE notificaciones n SET usuario_ci = u.ci FROM usuarios u WHERE n.usuario_id = u.id");
        DB::statement("UPDATE notificaciones n SET ticket_codigo = t.codigo FROM ticket t WHERE n.ticket_id = t.id");

        DB::statement("UPDATE configuraciones c SET usuario_ci = u.ci FROM usuarios u WHERE c.usuario_id = u.id");
        DB::statement("UPDATE bitacora_accesos b SET usuario_ci = u.ci FROM usuarios u WHERE b.usuario_id = u.id");
        DB::statement("UPDATE bot_interactions bi SET usuario_ci = u.ci FROM usuarios u WHERE bi.user_id = u.id");

        // Sanctum: convertir tokenable_id a VARCHAR(64) y migrar tokens activos de User
        DB::statement("ALTER TABLE personal_access_tokens ALTER COLUMN tokenable_id TYPE VARCHAR(64) USING tokenable_id::text");
        DB::statement("
            UPDATE personal_access_tokens pat
            SET tokenable_id = u.ci
            FROM usuarios u
            WHERE pat.tokenable_type = 'App\\Models\\User'
              AND pat.tokenable_id = u.id::text
        ");

        // Establecer NOT NULL donde corresponde
        DB::statement("ALTER TABLE ticket ALTER COLUMN solicitante_ci SET NOT NULL");
        DB::statement("ALTER TABLE ticket ALTER COLUMN prioridad_codigo SET NOT NULL");
        DB::statement("ALTER TABLE asignacion ALTER COLUMN ticket_codigo SET NOT NULL");
        DB::statement("ALTER TABLE usuario_asignacion ALTER COLUMN usuario_ci SET NOT NULL");
        DB::statement("ALTER TABLE usuario_categoria ALTER COLUMN usuario_ci SET NOT NULL");
        DB::statement("ALTER TABLE usuario_prioridad ALTER COLUMN usuario_ci SET NOT NULL");
        DB::statement("ALTER TABLE usuario_prioridad ALTER COLUMN prioridad_codigo SET NOT NULL");
        DB::statement("ALTER TABLE ticket_categoria ALTER COLUMN ticket_codigo SET NOT NULL");
        DB::statement("ALTER TABLE ticket_mensaje ALTER COLUMN ticket_codigo SET NOT NULL");
        DB::statement("ALTER TABLE tickets_history ALTER COLUMN ticket_codigo SET NOT NULL");
        DB::statement("ALTER TABLE tickets_history ALTER COLUMN usuario_ci SET NOT NULL");
        DB::statement("ALTER TABLE notificaciones ALTER COLUMN usuario_ci SET NOT NULL");

        // =====================================================================
        // 4. ELIMINAR TODAS LAS FKs Y PKs ANTIGUAS QUE DEPENDEN DE LOS `id`
        // =====================================================================
        DB::statement("ALTER TABLE asignacion DROP CONSTRAINT IF EXISTS asignacion_ticket_id_foreign");
        DB::statement("ALTER TABLE bitacora_accesos DROP CONSTRAINT IF EXISTS bitacora_accesos_usuario_id_foreign");
        DB::statement("ALTER TABLE bot_interactions DROP CONSTRAINT IF EXISTS bot_interactions_user_id_foreign");
        DB::statement("ALTER TABLE configuraciones DROP CONSTRAINT IF EXISTS configuraciones_usuario_id_foreign");
        DB::statement("ALTER TABLE notificaciones DROP CONSTRAINT IF EXISTS notificaciones_ticket_id_foreign");
        DB::statement("ALTER TABLE notificaciones DROP CONSTRAINT IF EXISTS notificaciones_usuario_id_foreign");
        DB::statement("ALTER TABLE ticket DROP CONSTRAINT IF EXISTS ticket_prioridad_id_foreign");
        DB::statement("ALTER TABLE ticket DROP CONSTRAINT IF EXISTS ticket_solicitante_id_foreign");
        DB::statement("ALTER TABLE ticket DROP CONSTRAINT IF EXISTS ticket_tecnico_asignado_id_foreign");
        DB::statement("ALTER TABLE ticket_categoria DROP CONSTRAINT IF EXISTS ticket_categoria_ticket_id_foreign");
        DB::statement("ALTER TABLE ticket_mensaje DROP CONSTRAINT IF EXISTS ticket_mensaje_ticket_id_foreign");
        DB::statement("ALTER TABLE ticket_mensaje DROP CONSTRAINT IF EXISTS ticket_mensaje_usuario_id_foreign");
        DB::statement("ALTER TABLE tickets_history DROP CONSTRAINT IF EXISTS tickets_history_changed_by_foreign");
        DB::statement("ALTER TABLE tickets_history DROP CONSTRAINT IF EXISTS tickets_history_ticket_id_foreign");
        DB::statement("ALTER TABLE usuario_asignacion DROP CONSTRAINT IF EXISTS usuario_asignacion_usuario_id_foreign");
        DB::statement("ALTER TABLE usuario_categoria DROP CONSTRAINT IF EXISTS usuario_categoria_usuario_id_foreign");
        DB::statement("ALTER TABLE usuario_prioridad DROP CONSTRAINT IF EXISTS usuario_prioridad_prioridad_id_foreign");
        DB::statement("ALTER TABLE usuario_prioridad DROP CONSTRAINT IF EXISTS usuario_prioridad_usuario_id_foreign");

        // Eliminar PKs antiguas (incluyendo pivotes)
        DB::statement("ALTER TABLE usuario_asignacion DROP CONSTRAINT IF EXISTS usuario_asignacion_pkey");
        DB::statement("ALTER TABLE usuario_categoria DROP CONSTRAINT IF EXISTS usuario_categoria_pkey");
        DB::statement("ALTER TABLE usuario_prioridad DROP CONSTRAINT IF EXISTS usuario_prioridad_pkey");
        DB::statement("ALTER TABLE ticket_categoria DROP CONSTRAINT IF EXISTS ticket_categoria_pkey");

        DB::statement("ALTER TABLE usuarios DROP CONSTRAINT IF EXISTS usuarios_pkey");
        DB::statement("ALTER TABLE ticket_prioridad DROP CONSTRAINT IF EXISTS ticket_prioridad_pkey");
        DB::statement("ALTER TABLE configuraciones DROP CONSTRAINT IF EXISTS configuraciones_pkey");
        DB::statement("ALTER TABLE ticket DROP CONSTRAINT IF EXISTS ticket_pkey");

        // Quitar UNIQUE redundantes que ahora pasan a ser PK
        DB::statement("ALTER TABLE usuarios DROP CONSTRAINT IF EXISTS usuarios_ci_unique");
        DB::statement("ALTER TABLE ticket_prioridad DROP CONSTRAINT IF EXISTS ticket_prioridad_codigo_unique");
        DB::statement("ALTER TABLE configuraciones DROP CONSTRAINT IF EXISTS configuraciones_clave_unique");
        DB::statement("ALTER TABLE ticket DROP CONSTRAINT IF EXISTS ticket_codigo_unique");

        // =====================================================================
        // 5. ELIMINAR COLUMNAS `id` Y FKs ANTIGUAS
        // =====================================================================
        DB::statement("ALTER TABLE usuarios DROP COLUMN id");
        DB::statement("ALTER TABLE ticket_prioridad DROP COLUMN id");
        DB::statement("ALTER TABLE configuraciones DROP COLUMN id, DROP COLUMN usuario_id");
        DB::statement("ALTER TABLE ticket DROP COLUMN id, DROP COLUMN solicitante_id, DROP COLUMN tecnico_asignado_id, DROP COLUMN prioridad_id");

        DB::statement("ALTER TABLE asignacion DROP COLUMN ticket_id");
        DB::statement("ALTER TABLE usuario_asignacion DROP COLUMN usuario_id");
        DB::statement("ALTER TABLE usuario_categoria DROP COLUMN usuario_id");
        DB::statement("ALTER TABLE usuario_prioridad DROP COLUMN usuario_id, DROP COLUMN prioridad_id");
        DB::statement("ALTER TABLE ticket_categoria DROP COLUMN ticket_id");
        DB::statement("ALTER TABLE ticket_mensaje DROP COLUMN ticket_id, DROP COLUMN usuario_id");
        DB::statement("ALTER TABLE tickets_history DROP COLUMN ticket_id, DROP COLUMN changed_by");
        DB::statement("ALTER TABLE notificaciones DROP COLUMN usuario_id, DROP COLUMN ticket_id");
        DB::statement("ALTER TABLE bitacora_accesos DROP COLUMN usuario_id");
        DB::statement("ALTER TABLE bot_interactions DROP COLUMN user_id");

        // =====================================================================
        // 6. TRADUCIR TABLAS Y COLUMNAS RESTANTES AL ESPAÑOL
        // =====================================================================
        // usuarios
        DB::statement("ALTER TABLE usuarios RENAME COLUMN moodle_user_id TO moodle_usuario_id");

        // ticket_mensaje
        DB::statement("ALTER TABLE ticket_mensaje RENAME COLUMN remitente_email TO remitente_correo");
        DB::statement("ALTER TABLE ticket_mensaje RENAME COLUMN correo_message_id TO correo_mensaje_id");

        // bitacora_accesos
        DB::statement("ALTER TABLE bitacora_accesos RENAME COLUMN user_agent TO agente_usuario");
        DB::statement("ALTER TABLE bitacora_accesos RENAME COLUMN created_at TO fecha_creacion");

        // tickets_history -> ticket_historial
        DB::statement("ALTER TABLE tickets_history RENAME TO ticket_historial");
        DB::statement("ALTER TABLE ticket_historial RENAME COLUMN old_status TO estado_anterior");
        DB::statement("ALTER TABLE ticket_historial RENAME COLUMN new_status TO estado_nuevo");
        DB::statement("ALTER TABLE ticket_historial RENAME COLUMN comments TO comentarios");
        DB::statement("ALTER TABLE ticket_historial RENAME COLUMN created_at TO fecha_creacion");
        DB::statement("ALTER TABLE ticket_historial RENAME COLUMN updated_at TO fecha_actualizacion");

        // bot_interactions -> interacciones_bot
        DB::statement("ALTER TABLE bot_interactions RENAME TO interacciones_bot");
        DB::statement("ALTER TABLE interacciones_bot RENAME COLUMN intent TO intencion");
        DB::statement("ALTER TABLE interacciones_bot RENAME COLUMN query TO consulta");
        DB::statement("ALTER TABLE interacciones_bot RENAME COLUMN response TO respuesta");
        DB::statement("ALTER TABLE interacciones_bot RENAME COLUMN resolved TO resuelto");
        DB::statement("ALTER TABLE interacciones_bot RENAME COLUMN resolution_time_seconds TO tiempo_resolucion_segundos");
        DB::statement("ALTER TABLE interacciones_bot RENAME COLUMN created_at TO fecha_creacion");
        DB::statement("ALTER TABLE interacciones_bot RENAME COLUMN updated_at TO fecha_actualizacion");

        // =====================================================================
        // 7. CREAR NUEVAS PKs, FKs (CON ON UPDATE CASCADE) E ÍNDICES
        // =====================================================================
        DB::statement("ALTER TABLE usuarios ADD CONSTRAINT usuarios_pkey PRIMARY KEY (ci)");
        DB::statement("ALTER TABLE ticket_prioridad ADD CONSTRAINT ticket_prioridad_pkey PRIMARY KEY (codigo)");
        DB::statement("ALTER TABLE configuraciones ADD CONSTRAINT configuraciones_pkey PRIMARY KEY (clave)");
        DB::statement("ALTER TABLE ticket ADD CONSTRAINT ticket_pkey PRIMARY KEY (codigo)");

        // PKs compuestas de tablas intermedias
        DB::statement("ALTER TABLE usuario_asignacion ADD CONSTRAINT usuario_asignacion_pkey PRIMARY KEY (asignacion_id, usuario_ci)");
        DB::statement("ALTER TABLE usuario_categoria ADD CONSTRAINT usuario_categoria_pkey PRIMARY KEY (usuario_ci, categoria_id)");
        DB::statement("ALTER TABLE usuario_prioridad ADD CONSTRAINT usuario_prioridad_pkey PRIMARY KEY (usuario_ci, prioridad_codigo)");
        DB::statement("ALTER TABLE ticket_categoria ADD CONSTRAINT ticket_categoria_pkey PRIMARY KEY (ticket_codigo, categoria_id)");

        // Nuevas llaves foráneas
        DB::statement("ALTER TABLE ticket ADD CONSTRAINT ticket_solicitante_ci_foreign FOREIGN KEY (solicitante_ci) REFERENCES usuarios(ci) ON UPDATE CASCADE");
        DB::statement("ALTER TABLE ticket ADD CONSTRAINT ticket_tecnico_asignado_ci_foreign FOREIGN KEY (tecnico_asignado_ci) REFERENCES usuarios(ci) ON UPDATE CASCADE ON DELETE SET NULL");
        DB::statement("ALTER TABLE ticket ADD CONSTRAINT ticket_prioridad_codigo_foreign FOREIGN KEY (prioridad_codigo) REFERENCES ticket_prioridad(codigo) ON UPDATE CASCADE");

        DB::statement("ALTER TABLE asignacion ADD CONSTRAINT asignacion_ticket_codigo_foreign FOREIGN KEY (ticket_codigo) REFERENCES ticket(codigo) ON UPDATE CASCADE ON DELETE CASCADE");
        DB::statement("ALTER TABLE usuario_asignacion ADD CONSTRAINT usuario_asignacion_usuario_ci_foreign FOREIGN KEY (usuario_ci) REFERENCES usuarios(ci) ON UPDATE CASCADE ON DELETE CASCADE");

        DB::statement("ALTER TABLE usuario_categoria ADD CONSTRAINT usuario_categoria_usuario_ci_foreign FOREIGN KEY (usuario_ci) REFERENCES usuarios(ci) ON UPDATE CASCADE ON DELETE CASCADE");
        DB::statement("ALTER TABLE usuario_prioridad ADD CONSTRAINT usuario_prioridad_usuario_ci_foreign FOREIGN KEY (usuario_ci) REFERENCES usuarios(ci) ON UPDATE CASCADE ON DELETE CASCADE");
        DB::statement("ALTER TABLE usuario_prioridad ADD CONSTRAINT usuario_prioridad_prioridad_codigo_foreign FOREIGN KEY (prioridad_codigo) REFERENCES ticket_prioridad(codigo) ON UPDATE CASCADE ON DELETE CASCADE");

        DB::statement("ALTER TABLE ticket_categoria ADD CONSTRAINT ticket_categoria_ticket_codigo_foreign FOREIGN KEY (ticket_codigo) REFERENCES ticket(codigo) ON UPDATE CASCADE ON DELETE CASCADE");

        DB::statement("ALTER TABLE ticket_mensaje ADD CONSTRAINT ticket_mensaje_ticket_codigo_foreign FOREIGN KEY (ticket_codigo) REFERENCES ticket(codigo) ON UPDATE CASCADE ON DELETE CASCADE");
        DB::statement("ALTER TABLE ticket_mensaje ADD CONSTRAINT ticket_mensaje_usuario_ci_foreign FOREIGN KEY (usuario_ci) REFERENCES usuarios(ci) ON UPDATE CASCADE ON DELETE SET NULL");

        DB::statement("ALTER TABLE ticket_historial ADD CONSTRAINT ticket_historial_ticket_codigo_foreign FOREIGN KEY (ticket_codigo) REFERENCES ticket(codigo) ON UPDATE CASCADE ON DELETE CASCADE");
        DB::statement("ALTER TABLE ticket_historial ADD CONSTRAINT ticket_historial_usuario_ci_foreign FOREIGN KEY (usuario_ci) REFERENCES usuarios(ci) ON UPDATE CASCADE");

        DB::statement("ALTER TABLE notificaciones ADD CONSTRAINT notificaciones_usuario_ci_foreign FOREIGN KEY (usuario_ci) REFERENCES usuarios(ci) ON UPDATE CASCADE ON DELETE CASCADE");
        DB::statement("ALTER TABLE notificaciones ADD CONSTRAINT notificaciones_ticket_codigo_foreign FOREIGN KEY (ticket_codigo) REFERENCES ticket(codigo) ON UPDATE CASCADE ON DELETE SET NULL");

        DB::statement("ALTER TABLE configuraciones ADD CONSTRAINT configuraciones_usuario_ci_foreign FOREIGN KEY (usuario_ci) REFERENCES usuarios(ci) ON UPDATE CASCADE ON DELETE SET NULL");
        DB::statement("ALTER TABLE bitacora_accesos ADD CONSTRAINT bitacora_accesos_usuario_ci_foreign FOREIGN KEY (usuario_ci) REFERENCES usuarios(ci) ON UPDATE CASCADE ON DELETE SET NULL");
        DB::statement("ALTER TABLE interacciones_bot ADD CONSTRAINT interacciones_bot_usuario_ci_foreign FOREIGN KEY (usuario_ci) REFERENCES usuarios(ci) ON UPDATE CASCADE ON DELETE SET NULL");

        // Índices de rendimiento
        DB::statement("CREATE INDEX IF NOT EXISTS asignacion_ticket_codigo_estado_idx ON asignacion (ticket_codigo, estado)");
        DB::statement("CREATE INDEX IF NOT EXISTS asignacion_ticket_codigo_tipo_idx ON asignacion (ticket_codigo, tipo_origen)");
        DB::statement("CREATE INDEX IF NOT EXISTS usuario_asignacion_usuario_ci_idx ON usuario_asignacion (usuario_ci)");
        DB::statement("CREATE INDEX IF NOT EXISTS notificaciones_usuario_ci_leida_idx ON notificaciones (usuario_ci, leida)");
        DB::statement("CREATE INDEX IF NOT EXISTS ticket_historial_ticket_codigo_idx ON ticket_historial (ticket_codigo)");

        // Secuencia atómica para generar códigos de ticket (TCK-XXX) sin depender de un `id`
        $maxNum = DB::selectOne("
            SELECT COALESCE(MAX(CAST(NULLIF(regexp_replace(codigo, '[^0-9]', '', 'g'), '') AS INTEGER)), 0) AS max_num
            FROM ticket
        ")->max_num ?? 0;
        $nextVal = max(1, (int) $maxNum + 1);
        DB::statement("CREATE SEQUENCE IF NOT EXISTS ticket_codigo_seq START WITH {$nextVal} INCREMENT BY 1");
        DB::statement("SELECT setval('ticket_codigo_seq', {$nextVal}, false)");
    }

    public function down()
    {
        throw new \RuntimeException('Esta migración de refactorización estructural (PKs naturales y traducción total) no soporta rollback automático; restaure desde respaldo SQL.');
    }
}
