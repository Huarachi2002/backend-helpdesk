<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConfiguracionesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('configuraciones', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 80)->unique();
            $table->text('valor');
            $table->string('tipo', 30)->default('string'); // integer, string, boolean, json
            $table->string('grupo', 50)->default('notificaciones'); // notificaciones, general, sla
            $table->string('descripcion', 255)->nullable();
            $table->timestampTz('fecha_creacion')->useCurrent();
            $table->timestampTz('fecha_actualizacion')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('configuraciones');
    }
}
