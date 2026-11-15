<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTicketPrioridadTable extends Migration
{
    public function up()
    {
        Schema::create('ticket_prioridad', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 50);
            $table->string('codigo', 20)->unique();
            $table->integer('peso_nivel');
            $table->integer('sla_horas_resolucion');
            $table->boolean('activo')->default(true);
            $table->timestampTz('fecha_creacion')->useCurrent();
            $table->timestampTz('fecha_actualizacion')->useCurrent();
        });
    }

    public function down()
    {
        Schema::dropIfExists('ticket_prioridad');
    }
}
