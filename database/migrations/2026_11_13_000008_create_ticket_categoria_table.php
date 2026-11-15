<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTicketCategoriaTable extends Migration
{
    public function up()
    {
        Schema::create('ticket_categoria', function (Blueprint $table) {
            $table->foreignId('ticket_id')->constrained('ticket')->cascadeOnDelete();
            $table->foreignId('categoria_id')->constrained('categorias')->cascadeOnDelete();

            $table->primary(['ticket_id', 'categoria_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('ticket_categoria');
    }
}
