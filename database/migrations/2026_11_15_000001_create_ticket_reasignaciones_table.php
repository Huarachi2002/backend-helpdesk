<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTicketReasignacionesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ticket_reasignaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('ticket')->cascadeOnDelete();
            $table->foreignId('solicitante_id')->constrained('usuarios');
            $table->foreignId('tecnico_anterior_id')->nullable()->constrained('usuarios');
            $table->foreignId('tecnico_propuesto_id')->constrained('usuarios');
            $table->text('motivo');
            $table->string('estado', 30)->default('PENDIENTE'); // PENDIENTE, APROBADA, RECHAZADA
            $table->foreignId('revisado_por')->nullable()->constrained('usuarios');
            $table->text('motivo_resolucion')->nullable();
            $table->timestampTz('fecha_solicitud')->useCurrent();
            $table->timestampTz('fecha_resolucion')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ticket_reasignaciones');
    }
}
