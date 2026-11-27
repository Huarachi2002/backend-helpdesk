<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVisitasPaginaTable extends Migration
{
    /**
     * Crea la tabla global de contador de accesos por vista/página web.
     * Utiliza `ruta` como clave primaria natural alineada al esquema en español.
     */
    public function up()
    {
        if (!Schema::hasTable('visitas_pagina')) {
            Schema::create('visitas_pagina', function (Blueprint $table) {
                $table->string('ruta', 255)->primary();
                $table->string('titulo', 120)->nullable();
                $table->unsignedBigInteger('visitas')->default(0);
                $table->timestamp('ultima_visita')->nullable();
                $table->timestamp('fecha_creacion')->nullable();
                $table->timestamp('fecha_actualizacion')->nullable();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('visitas_pagina');
    }
}
