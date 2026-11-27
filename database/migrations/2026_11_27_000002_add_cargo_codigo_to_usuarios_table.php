<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cargo del organigrama que ocupa cada usuario del personal (DIRECTOR / TECNICO).
 * Es nullable porque la tabla usuarios también guarda Estudiantes y Docentes;
 * para el personal el cargo es obligatorio y se valida en la capa de aplicación.
 */
class AddCargoCodigoToUsuariosTable extends Migration
{
    public function up()
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->string('cargo_codigo', 30)->nullable()->after('rol_id');

            $table->foreign('cargo_codigo')
                ->references('codigo')->on('cargos')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->index('cargo_codigo');
        });
    }

    public function down()
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropForeign(['cargo_codigo']);
            $table->dropIndex(['cargo_codigo']);
            $table->dropColumn('cargo_codigo');
        });
    }
}
