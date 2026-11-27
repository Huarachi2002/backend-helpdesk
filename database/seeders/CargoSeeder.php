<?php

namespace Database\Seeders;

use App\Models\Cargo;
use Illuminate\Database\Seeder;

/**
 * Siembra los 8 cargos oficiales de la estructura organizacional DEDTE (idempotente).
 */
class CargoSeeder extends Seeder
{
    public function run()
    {
        $cargos = [
            [
                'codigo'                => 'DEDTE-JD-001',
                'nombre'                => 'Jefe/a del Departamento DEDTE',
                'seccion'               => 'Dirección Departamental',
                'cargo_superior_codigo' => null,
                'dependencia_externa'   => 'DICAA — Vicerrectorado',
                'nivel'                 => 1,
                'orden'                 => 1,
                'activo'                => true,
            ],
            [
                'codigo'                => 'DEDTE-CAP-002',
                'nombre'                => 'Coordinador/a Académico/a y Pedagógico/a',
                'seccion'               => 'Sec. I — Académica y Pedagógica',
                'cargo_superior_codigo' => 'DEDTE-JD-001',
                'dependencia_externa'   => null,
                'nivel'                 => 2,
                'orden'                 => 1,
                'activo'                => true,
            ],
            [
                'codigo'                => 'DEDTE-CTE-006',
                'nombre'                => 'Coordinador/a de Tecnología Educativa',
                'seccion'               => 'Sec. II — Tecnología Educativa',
                'cargo_superior_codigo' => 'DEDTE-JD-001',
                'dependencia_externa'   => null,
                'nivel'                 => 2,
                'orden'                 => 2,
                'activo'                => true,
            ],
            [
                'codigo'                => 'DEDTE-RDI-003',
                'nombre'                => 'Responsable de Diseño Instruccional y Contenidos Virtuales',
                'seccion'               => 'Sec. I — Académica y Pedagógica',
                'cargo_superior_codigo' => 'DEDTE-CAP-002',
                'dependencia_externa'   => null,
                'nivel'                 => 3,
                'orden'                 => 1,
                'activo'                => true,
            ],
            [
                'codigo'                => 'DEDTE-ASA-004',
                'nombre'                => 'Analista de Seguimiento Académico de Aulas y Carreras Virtuales',
                'seccion'               => 'Sec. I — Académica y Pedagógica',
                'cargo_superior_codigo' => 'DEDTE-CAP-002',
                'dependencia_externa'   => null,
                'nivel'                 => 3,
                'orden'                 => 2,
                'activo'                => true,
            ],
            [
                'codigo'                => 'DEDTE-RCC-005',
                'nombre'                => 'Responsable de Capacitación y Certificación Docente',
                'seccion'               => 'Sec. I — Académica y Pedagógica',
                'cargo_superior_codigo' => 'DEDTE-CAP-002',
                'dependencia_externa'   => null,
                'nivel'                 => 3,
                'orden'                 => 3,
                'activo'                => true,
            ],
            [
                'codigo'                => 'DEDTE-APV-007',
                'nombre'                => 'Administrador/a de Plataformas Virtuales y Sistemas Web',
                'seccion'               => 'Sec. II — Tecnología Educativa',
                'cargo_superior_codigo' => 'DEDTE-CTE-006',
                'dependencia_externa'   => null,
                'nivel'                 => 3,
                'orden'                 => 1,
                'activo'                => true,
            ],
            [
                'codigo'                => 'DEDTE-TSE-008',
                'nombre'                => 'Técnico/a de Soporte en Educación Virtual',
                'seccion'               => 'Sec. II — Tecnología Educativa',
                'cargo_superior_codigo' => 'DEDTE-CTE-006',
                'dependencia_externa'   => null,
                'nivel'                 => 3,
                'orden'                 => 2,
                'activo'                => true,
            ],
        ];

        foreach ($cargos as $cargo) {
            Cargo::updateOrCreate(['codigo' => $cargo['codigo']], $cargo);
        }
    }
}
