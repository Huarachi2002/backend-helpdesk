<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateTicketAsignacionesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1. Crear la nueva tabla simplificada ticket_asignaciones
        Schema::create('ticket_asignaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('ticket')->cascadeOnDelete();
            $table->foreignId('tecnico_id')->constrained('usuarios');
            $table->boolean('activo')->default(false);
            $table->boolean('solicitud_pendiente')->default(false);
            $table->text('motivo')->nullable();
            $table->text('motivo_resolucion')->nullable();
            $table->timestampTz('fecha_inicio')->nullable();
            $table->timestampTz('fecha_fin')->nullable();
            $table->timestamps();

            // Índices para consultas eficientes
            $table->index(['ticket_id', 'activo']);
            $table->index(['ticket_id', 'solicitud_pendiente']);
        });

        // 2. Poblar automáticamente asignaciones activas para tickets existentes con técnico asignado
        if (Schema::hasTable('ticket')) {
            $ticketsConTecnico = DB::table('ticket')
                ->whereNotNull('tecnico_asignado_id')
                ->get(['id', 'tecnico_asignado_id', 'fecha_creacion']);

            foreach ($ticketsConTecnico as $t) {
                DB::table('ticket_asignaciones')->insert([
                    'ticket_id'           => $t->id,
                    'tecnico_id'          => $t->tecnico_asignado_id,
                    'activo'              => true,
                    'solicitud_pendiente' => false,
                    'motivo'              => 'Asignación técnica inicial del ticket.',
                    'fecha_inicio'        => $t->fecha_creacion ?: now(),
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);
            }
        }

        // 3. Eliminar la tabla previa de 4 relaciones
        Schema::dropIfExists('ticket_reasignaciones');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ticket_asignaciones');
    }
}
