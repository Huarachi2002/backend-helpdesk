<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Estructura organizacional (organigrama DEDTE).
 * Árbol auto-referenciado de cargos con PK natural = código institucional.
 */
class CreateCargosTable extends Migration
{
    public function up()
    {
        Schema::create('cargos', function (Blueprint $table) {
            $table->string('codigo', 30)->primary();
            $table->string('nombre', 150);
            $table->string('seccion', 80);
            $table->string('cargo_superior_codigo', 30)->nullable();
            $table->string('dependencia_externa', 150)->nullable();
            $table->smallInteger('nivel')->default(1);
            $table->smallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestampTz('fecha_creacion')->useCurrent();
            $table->timestampTz('fecha_actualizacion')->useCurrent();

            $table->index(['cargo_superior_codigo', 'orden']);
            $table->index('nivel');
        });

        Schema::table('cargos', function (Blueprint $table) {
            $table->foreign('cargo_superior_codigo')
                ->references('codigo')->on('cargos')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

        // Estructura institucional del DEDTE (datos de referencia aprobados por el cliente).
        $now = now();
        $cargos = [
            ['DEDTE-JD-001',  'Jefe/a del Departamento DEDTE',                                   'Dirección Departamental',            null,            'DICAA — Vicerrectorado', 1, 1],
            ['DEDTE-CAP-002', 'Coordinador/a Académico/a y Pedagógico/a',                        'Sec. I — Académica y Pedagógica',    'DEDTE-JD-001',  null, 2, 1],
            ['DEDTE-RDI-003', 'Responsable de Diseño Instruccional y Contenidos Virtuales',      'Sec. I — Académica y Pedagógica',    'DEDTE-CAP-002', null, 3, 1],
            ['DEDTE-ASA-004', 'Analista de Seguimiento Académico de Aulas y Carreras Virtuales', 'Sec. I — Académica y Pedagógica',    'DEDTE-CAP-002', null, 3, 2],
            ['DEDTE-RCC-005', 'Responsable de Capacitación y Certificación Docente',             'Sec. I — Académica y Pedagógica',    'DEDTE-CAP-002', null, 3, 3],
            ['DEDTE-CTE-006', 'Coordinador/a de Tecnología Educativa',                           'Sec. II — Tecnología Educativa',     'DEDTE-JD-001',  null, 2, 2],
            ['DEDTE-APV-007', 'Administrador/a de Plataformas Virtuales y Sistemas Web',         'Sec. II — Tecnología Educativa',     'DEDTE-CTE-006', null, 3, 1],
            ['DEDTE-TSE-008', 'Técnico/a de Soporte en Educación Virtual',                       'Sec. II — Tecnología Educativa',     'DEDTE-CTE-006', null, 3, 2],
        ];

        foreach ($cargos as [$codigo, $nombre, $seccion, $superior, $externa, $nivel, $orden]) {
            DB::table('cargos')->insert([
                'codigo'                => $codigo,
                'nombre'                => $nombre,
                'seccion'               => $seccion,
                'cargo_superior_codigo' => $superior,
                'dependencia_externa'   => $externa,
                'nivel'                 => $nivel,
                'orden'                 => $orden,
                'activo'                => true,
                'fecha_creacion'        => $now,
                'fecha_actualizacion'   => $now,
            ]);
        }
    }

    public function down()
    {
        Schema::dropIfExists('cargos');
    }
}
