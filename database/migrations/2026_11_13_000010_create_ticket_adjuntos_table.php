<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTicketAdjuntosTable extends Migration
{
    public function up()
    {
        Schema::create('ticket_adjuntos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mensaje_id')->constrained('ticket_mensaje')->cascadeOnDelete();
            $table->string('nombre_original', 255);
            $table->string('extension', 20);
            $table->string('nombre_almacenado', 255);
            $table->text('ruta_almacenado');
            $table->timestampTz('fecha_creacion')->useCurrent();
        });
    }

    public function down()
    {
        Schema::dropIfExists('ticket_adjuntos');
    }
}
