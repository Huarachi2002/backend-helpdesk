<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBitacoraAccesosTable extends Migration
{
    public function up()
    {
        Schema::create('bitacora_accesos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->foreign('usuario_id')->references('id')->on('usuarios')->onDelete('set null');
            $table->string('tipo_evento', 50); // LOGIN_OK, LOGIN_FAIL, ACCESS, LOGOUT
            $table->string('ip', 45)->nullable();
            $table->string('recurso', 255)->nullable();
            $table->string('metodo', 10)->nullable();
            $table->text('descripcion')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('correo_intento', 255)->nullable(); // para LOGIN_FAIL
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down()
    {
        Schema::dropIfExists('bitacora_accesos');
    }
}
