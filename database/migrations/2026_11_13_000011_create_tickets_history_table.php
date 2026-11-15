<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTicketsHistoryTable extends Migration
{
    public function up()
    {
        Schema::create('tickets_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('ticket')->cascadeOnDelete();
            $table->foreignId('changed_by')->constrained('usuarios');
            $table->string('old_status')->nullable();
            $table->string('new_status');
            $table->text('comments')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('tickets_history');
    }
}
