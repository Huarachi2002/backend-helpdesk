<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUltimoUsuarioCiToVisitasPaginaTable extends Migration
{
    /**
     * Agrega relación 1:N (usuarios 1 -> N visitas_pagina) registrando el último
     * usuario autenticado que visitó cada vista (nullable para rutas públicas como /login).
     */
    public function up()
    {
        Schema::table('visitas_pagina', function (Blueprint $table) {
            if (!Schema::hasColumn('visitas_pagina', 'ultimo_usuario_ci')) {
                $table->string('ultimo_usuario_ci', 30)->nullable()->after('visitas');
                $table->index('ultimo_usuario_ci', 'visitas_pagina_ultimo_usuario_ci_idx');
                $table->foreign('ultimo_usuario_ci', 'visitas_pagina_ultimo_usuario_ci_fk')
                    ->references('ci')
                    ->on('usuarios')
                    ->onDelete('set null')
                    ->onUpdate('cascade');
            }
        });
    }

    public function down()
    {
        Schema::table('visitas_pagina', function (Blueprint $table) {
            if (Schema::hasColumn('visitas_pagina', 'ultimo_usuario_ci')) {
                $table->dropForeign('visitas_pagina_ultimo_usuario_ci_fk');
                $table->dropIndex('visitas_pagina_ultimo_usuario_ci_idx');
                $table->dropColumn('ultimo_usuario_ci');
            }
        });
    }
}
