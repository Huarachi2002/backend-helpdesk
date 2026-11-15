<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsuariosTable extends Migration
{
    public function up()
    {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rol_id')->constrained('roles');
            $table->string('nombre', 100);
            $table->string('apellido', 100)->nullable();
            $table->string('correo', 150)->unique();
            $table->string('contrasenia', 255)->nullable();
            $table->boolean('sesion')->default(false);
            $table->integer('moodle_user_id')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestampTz('fecha_creacion')->useCurrent();
            $table->timestampTz('fecha_actualizacion')->useCurrent();
        });
    }

    public function down()
    {
        Schema::dropIfExists('usuarios');
    }
}
