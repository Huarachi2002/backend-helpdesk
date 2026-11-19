<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsuarioNotificacionPrefsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('usuario_notificacion_prefs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->string('tipo_evento', 60); // TICKET_CREADO, CAMBIO_ESTADO, RECORDATORIO_PENDIENTE, REASIGNACION, TICKET_SIN_RESPUESTA
            $table->boolean('canal_email')->default(true);
            $table->boolean('canal_in_app')->default(true);
            $table->boolean('canal_whatsapp')->default(false);
            $table->boolean('canal_telegram')->default(false);
            $table->timestampTz('fecha_creacion')->useCurrent();
            $table->timestampTz('fecha_actualizacion')->useCurrent();

            $table->unique(['usuario_id', 'tipo_evento']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('usuario_notificacion_prefs');
    }
}
