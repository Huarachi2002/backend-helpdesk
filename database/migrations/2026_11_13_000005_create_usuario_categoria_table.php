<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsuarioCategoriaTable extends Migration
{
    public function up()
    {
        Schema::create('usuario_categoria', function (Blueprint $table) {
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->foreignId('categoria_id')->constrained('categorias')->cascadeOnDelete();
            $table->timestampTz('asignado_en')->useCurrent();

            $table->primary(['usuario_id', 'categoria_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('usuario_categoria');
    }
}
