<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBotInteractionsTable extends Migration
{
    public function up()
    {
        Schema::create('bot_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('usuarios');
            $table->string('intent');
            $table->text('query');
            $table->text('response');
            $table->boolean('resolved')->default(false);
            $table->integer('resolution_time_seconds')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('bot_interactions');
    }
}
