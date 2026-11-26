<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrige las fechas guardadas mientras la aplicación estaba en UTC.
 *
 * Laravel escribía la hora UTC sin zona (ej. "17:01") y PostgreSQL, con su
 * sesión en America/La_Paz, la guardaba como "17:01 -04": 4 horas adelantada.
 * Esta migración reinterpreta esos valores como UTC.
 *
 * Solo incluye columnas que escribe Laravel. Las que llena PostgreSQL con su
 * DEFAULT now() (usuario_categoria/usuario_prioridad.asignado_en,
 * bitacora_accesos.created_at, failed_jobs.failed_at) ya estaban bien.
 * En una base nueva (migrate:fresh --seed) las tablas están vacías y no hace nada.
 */
class CorregirZonaHorariaFechas extends Migration
{
    private const ZONA = 'America/La_Paz';

    /** Columnas "timestamp with time zone" escritas por Laravel. */
    private const CON_ZONA = [
        'roles'              => ['fecha_creacion', 'fecha_actualizacion'],
        'usuarios'           => ['fecha_creacion', 'fecha_actualizacion'],
        'categorias'         => ['fecha_creacion', 'fecha_actualizacion'],
        'ticket_prioridad'   => ['fecha_creacion', 'fecha_actualizacion'],
        'configuraciones'    => ['fecha_creacion', 'fecha_actualizacion'],
        'notificaciones'     => ['fecha_creacion', 'fecha_actualizacion', 'fecha_leida'],
        'ticket'             => ['fecha_creacion', 'fecha_actualizacion', 'fecha_respuesta', 'fecha_resuelto', 'fecha_cierre'],
        'ticket_mensaje'     => ['fecha_creacion'],
        'ticket_adjuntos'    => ['fecha_creacion'],
        'ticket_asignaciones' => ['fecha_inicio', 'fecha_fin'],
    ];

    /** Columnas "timestamp without time zone" escritas por Laravel (hora UTC). */
    private const SIN_ZONA = [
        'tickets_history'        => ['created_at', 'updated_at'],
        'ticket_asignaciones'    => ['created_at', 'updated_at'],
        'bot_interactions'       => ['created_at', 'updated_at'],
        'personal_access_tokens' => ['created_at', 'updated_at', 'last_used_at'],
        'password_resets'        => ['created_at'],
    ];

    public function up()
    {
        $this->convertir(true);
    }

    public function down()
    {
        $this->convertir(false);
    }

    private function convertir(bool $corregir): void
    {
        $zona = self::ZONA;
        [$desde, $hacia] = $corregir ? [$zona, 'UTC'] : ['UTC', $zona];

        DB::transaction(function () use ($desde, $hacia) {
            foreach (self::CON_ZONA as $tabla => $columnas) {
                foreach ($this->existentes($tabla, $columnas) as $col) {
                    DB::statement("UPDATE \"{$tabla}\" SET \"{$col}\" = (\"{$col}\" AT TIME ZONE '{$desde}') AT TIME ZONE '{$hacia}' WHERE \"{$col}\" IS NOT NULL");
                }
            }

            foreach (self::SIN_ZONA as $tabla => $columnas) {
                foreach ($this->existentes($tabla, $columnas) as $col) {
                    // Para columnas sin zona el sentido es el inverso: de hora UTC a hora local.
                    DB::statement("UPDATE \"{$tabla}\" SET \"{$col}\" = (\"{$col}\" AT TIME ZONE '{$hacia}') AT TIME ZONE '{$desde}' WHERE \"{$col}\" IS NOT NULL");
                }
            }
        });
    }

    private function existentes(string $tabla, array $columnas): array
    {
        if (!Schema::hasTable($tabla)) {
            return [];
        }

        return array_values(array_filter($columnas, fn ($c) => Schema::hasColumn($tabla, $c)));
    }
}
