<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsuarioPrioridadTable extends Migration
{
    public function up()
    {
        Schema::create('usuario_prioridad', function (Blueprint $table) {
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->foreignId('prioridad_id')->constrained('ticket_prioridad')->cascadeOnDelete();
            $table->timestampTz('asignado_en')->useCurrent();

            $table->primary(['usuario_id', 'prioridad_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('usuario_prioridad');
    }
}
