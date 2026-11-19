<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNotificacionesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->string('tipo', 60); // TICKET_CREADO, CAMBIO_ESTADO, RECORDATORIO_PENDIENTE, SOLICITUD_REASIGNACION, REASIGNACION_APROBADA, REASIGNACION_RECHAZADA, TICKET_SIN_RESPUESTA
            $table->string('titulo', 191);
            $table->text('mensaje');
            $table->foreignId('ticket_id')->nullable()->constrained('ticket')->nullOnDelete();
            $table->json('datos')->nullable();
            $table->boolean('leida')->default(false);
            $table->timestampTz('fecha_leida')->nullable();
            $table->timestampTz('fecha_creacion')->useCurrent();
            $table->timestampTz('fecha_actualizacion')->useCurrent();

            $table->index(['usuario_id', 'leida']);
            $table->index(['usuario_id', 'fecha_creacion']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('notificaciones');
    }
}
