<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Relevamiento de tickets (movimiento vertical en el organigrama).
 * - ticket_asignaciones: tipo_movimiento, realizado_por_id, cargo_destino_codigo.
 * - ticket: nivel_relevamiento.
 * - configuraciones: relevamiento_permitir_saltar_niveles.
 */
class AddRelevamientoFields extends Migration
{
    public function up()
    {
        Schema::table('ticket_asignaciones', function (Blueprint $table) {
            $table->string('tipo_movimiento', 30)->default('ASIGNACION')->after('solicitud_pendiente');
            $table->foreignId('realizado_por_id')->nullable()->after('tipo_movimiento')
                ->constrained('usuarios')->nullOnDelete();
            $table->string('cargo_destino_codigo', 30)->nullable()->after('realizado_por_id');

            $table->foreign('cargo_destino_codigo')
                ->references('codigo')->on('cargos')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });

        Schema::table('ticket', function (Blueprint $table) {
            $table->smallInteger('nivel_relevamiento')->default(0);
        });

        // Backfill: la primera asignación de cada ticket es ASIGNACION, el resto REASIGNACION.
        DB::statement("
            UPDATE ticket_asignaciones ta
            SET tipo_movimiento = 'REASIGNACION'
            WHERE ta.id <> (
                SELECT MIN(t2.id) FROM ticket_asignaciones t2 WHERE t2.ticket_id = ta.ticket_id
            )
        ");

        // Cargo destino = cargo actual del técnico (si ya tiene uno asignado).
        DB::statement("
            UPDATE ticket_asignaciones ta
            SET cargo_destino_codigo = u.cargo_codigo
            FROM usuarios u
            WHERE u.id = ta.tecnico_id AND u.cargo_codigo IS NOT NULL
        ");

        if (Schema::hasTable('configuraciones')) {
            $exists = DB::table('configuraciones')->where('clave', 'relevamiento_permitir_saltar_niveles')->exists();
            if (!$exists) {
                DB::table('configuraciones')->insert([
                    'clave'               => 'relevamiento_permitir_saltar_niveles',
                    'valor'               => '0',
                    'tipo'                => 'boolean',
                    'grupo'               => 'relevamiento',
                    'descripcion'         => 'Permite a los Técnicos relevar tickets saltando niveles de la estructura (los Administradores siempre pueden). Desactivado = relevamiento secuencial al nivel contiguo.',
                    'fecha_creacion'      => now(),
                    'fecha_actualizacion' => now(),
                ]);
            }
        }
    }

    public function down()
    {
        DB::table('configuraciones')->where('clave', 'relevamiento_permitir_saltar_niveles')->delete();

        Schema::table('ticket', function (Blueprint $table) {
            $table->dropColumn('nivel_relevamiento');
        });

        Schema::table('ticket_asignaciones', function (Blueprint $table) {
            $table->dropForeign(['cargo_destino_codigo']);
            $table->dropForeign(['realizado_por_id']);
            $table->dropColumn(['tipo_movimiento', 'realizado_por_id', 'cargo_destino_codigo']);
        });
    }
}
