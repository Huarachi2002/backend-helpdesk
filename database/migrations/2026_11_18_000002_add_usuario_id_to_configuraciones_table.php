<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddUsuarioIdToConfiguracionesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('configuraciones', function (Blueprint $table) {
            $table->foreignId('usuario_id')
                ->nullable()
                ->after('id')
                ->constrained('usuarios')
                ->nullOnDelete();
        });

        // Asignar el Administrador / Director (ID: 1) como responsable inicial de las configuraciones existentes
        $directorId = DB::table('usuarios')->where('id', 1)->value('id') 
            ?? DB::table('usuarios')->value('id');

        if ($directorId) {
            DB::table('configuraciones')->whereNull('usuario_id')->update([
                'usuario_id' => $directorId,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('configuraciones', function (Blueprint $table) {
            $table->dropForeign(['usuario_id']);
            $table->dropColumn('usuario_id');
        });
    }
}
