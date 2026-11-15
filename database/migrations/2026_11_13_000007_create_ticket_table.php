<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTicketTable extends Migration
{
    public function up()
    {
        Schema::create('ticket', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->foreignId('solicitante_id')->constrained('usuarios');
            $table->foreignId('tecnico_asignado_id')->nullable()->constrained('usuarios');
            $table->foreignId('prioridad_id')->constrained('ticket_prioridad');
            $table->string('asunto', 200);
            $table->text('descripcion');
            $table->string('estado', 40); // 'EN_ESPERA', 'ESPERANDO_POR_CLIENTE', 'CLIENTE_ESPERANDO_RESPUESTA', 'RESUELTO', 'CERRADO'
            $table->string('correo_hilo', 255)->nullable();
            $table->timestampTz('fecha_creacion')->useCurrent();
            $table->timestampTz('fecha_respuesta')->nullable();
            $table->timestampTz('fecha_resuelto')->nullable();
            $table->timestampTz('fecha_cierre')->nullable();
            $table->timestampTz('fecha_actualizacion')->useCurrent();
        });
    }

    public function down()
    {
        Schema::dropIfExists('ticket');
    }
}
