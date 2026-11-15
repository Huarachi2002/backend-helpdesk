<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTicketMensajeTable extends Migration
{
    public function up()
    {
        Schema::create('ticket_mensaje', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('ticket')->cascadeOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios');
            $table->string('tipo_emisor', 20); // 'SOPORTE' o 'CLIENTE'
            $table->string('canal_origen', 20); // 'WEB' o 'EMAIL'
            $table->string('remitente_email', 150);
            $table->string('remitente_nombre', 150)->nullable();
            $table->text('cuerpo_mensaje');
            $table->string('correo_message_id', 255)->nullable();
            $table->timestampTz('fecha_creacion')->useCurrent();
        });
    }

    public function down()
    {
        Schema::dropIfExists('ticket_mensaje');
    }
}
