<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Refactorización del modelo de asignaciones:
 * - Crea la tabla `asignacion` (operación/movimiento sobre un ticket: asignación inicial,
 *   solicitud de reasignación, reasignación directa, relevamiento ascendente/descendente y devolución).
 *   Estados estrictos: PENDIENTE, APROBADA, RECHAZADA.
 * - Crea la tabla intermedia pura `usuario_asignacion` SIN columna `id`, con clave primaria compuesta
 *   únicamente por (asignacion_id, usuario_id) y atributos `rol_participacion` y `fecha_accion`.
 * - Migra los datos existentes desde `ticket_asignaciones` preservando el historial.
 * - Elimina la tabla anterior `ticket_asignaciones`.
 */
class RefactorAsignacionAndUsuarioAsignacionTables extends Migration
{
    public function up()
    {
        Schema::create('asignacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('ticket')->cascadeOnDelete();
            $table->string('estado', 30)->default('APROBADA');
            $table->string('tipo_origen', 40)->default('AUTOMATICA');
            $table->text('motivo_solicitud')->nullable();
            $table->text('motivo_resolucion')->nullable();
            $table->string('cargo_origen_codigo', 30)->nullable();
            $table->string('cargo_destino_codigo', 30)->nullable();
            $table->timestampTz('fecha_creacion')->useCurrent();
            $table->timestampTz('fecha_resolucion')->nullable();

            $table->foreign('cargo_origen_codigo')
                ->references('codigo')->on('cargos')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('cargo_destino_codigo')
                ->references('codigo')->on('cargos')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->index(['ticket_id', 'estado'], 'asignacion_ticket_estado_idx');
            $table->index(['ticket_id', 'tipo_origen'], 'asignacion_ticket_tipo_idx');
        });

        // Restricción CHECK en PostgreSQL para asegurar únicamente los 3 estados válidos
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE asignacion ADD CONSTRAINT asignacion_estado_check CHECK (estado IN ('PENDIENTE', 'APROBADA', 'RECHAZADA'))");
        }

        // Tabla intermedia pura: PK compuesta únicamente por (asignacion_id, usuario_id)
        Schema::create('usuario_asignacion', function (Blueprint $table) {
            $table->foreignId('asignacion_id')->constrained('asignacion')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->string('rol_participacion', 30);
            $table->timestampTz('fecha_accion')->useCurrent();

            $table->primary(['asignacion_id', 'usuario_id'], 'usuario_asignacion_pkey');
            $table->index('usuario_id', 'usuario_asignacion_usuario_idx');
            $table->index('rol_participacion', 'usuario_asignacion_rol_idx');
        });

        // Migrar datos existentes de ticket_asignaciones si la tabla existe
        if (Schema::hasTable('ticket_asignaciones')) {
            $hasTipoMov = Schema::hasColumn('ticket_asignaciones', 'tipo_movimiento');
            $hasRealizadoPor = Schema::hasColumn('ticket_asignaciones', 'realizado_por_id');
            $hasCargoDestino = Schema::hasColumn('ticket_asignaciones', 'cargo_destino_codigo');

            $rows = DB::table('ticket_asignaciones')
                ->orderBy('ticket_id')
                ->orderBy('id')
                ->get();

            // Cache de cargos actuales por usuario para completar cargo_destino_codigo si faltaba
            $userCargos = DB::table('usuarios')->pluck('cargo_codigo', 'id')->all();

            // Rastrea la última asignación aprobada por ticket para reconstruir origen y cargo_origen
            $prevByTicket = [];

            foreach ($rows as $row) {
                $tipoMov = $hasTipoMov ? strtoupper((string) ($row->tipo_movimiento ?? 'ASIGNACION')) : 'ASIGNACION';
                $motivo = $row->motivo ?? null;
                $motivoRes = $row->motivo_resolucion ?? null;
                $motivoLower = mb_strtolower((string) $motivo);
                $motivoResLower = mb_strtolower((string) $motivoRes);

                // 1. Determinar estado (solo PENDIENTE, APROBADA o RECHAZADA)
                if (!empty($row->solicitud_pendiente)) {
                    $estado = 'PENDIENTE';
                } elseif (!empty($row->activo)) {
                    $estado = 'APROBADA';
                } elseif (in_array($tipoMov, ['ASIGNACION', 'RELEVAMIENTO_ASCENDENTE', 'RELEVAMIENTO_DESCENDENTE', 'DEVOLUCION'], true)) {
                    $estado = 'APROBADA';
                } else {
                    // En REASIGNACION inactiva: detectar si fue rechazada/cancelada o si fue aprobada y luego superada
                    $pareceRechazo = str_contains($motivoResLower, 'deneg')
                        || str_contains($motivoResLower, 'rechaz')
                        || str_contains($motivoResLower, 'ejecutada directamente');
                    $estado = $pareceRechazo ? 'RECHAZADA' : 'APROBADA';
                }

                // 2. Determinar tipo_origen
                if ($tipoMov === 'ASIGNACION') {
                    $tipoOrigen = 'AUTOMATICA';
                } elseif (in_array($tipoMov, ['RELEVAMIENTO_ASCENDENTE', 'RELEVAMIENTO_DESCENDENTE', 'DEVOLUCION'], true)) {
                    $tipoOrigen = $tipoMov;
                } else {
                    // REASIGNACION
                    $esDirecta = $estado === 'APROBADA'
                        && empty($motivoRes)
                        && (str_contains($motivoLower, 'directa') || empty($motivo));
                    $tipoOrigen = $esDirecta ? 'REASIGNACION_DIRECTA' : 'SOLICITUD_REASIGNACION';
                }

                $prev = $prevByTicket[$row->ticket_id] ?? null;
                $cargoDestino = $hasCargoDestino ? ($row->cargo_destino_codigo ?? null) : null;
                if (!$cargoDestino && !empty($row->tecnico_id)) {
                    $cargoDestino = $userCargos[$row->tecnico_id] ?? null;
                }
                $cargoOrigen = $prev['cargo_codigo'] ?? null;

                $fechaCreacion = $row->fecha_inicio ?? $row->created_at ?? now();
                $fechaResolucion = $estado === 'PENDIENTE'
                    ? null
                    : ($row->fecha_fin ?? $row->fecha_inicio ?? $row->updated_at ?? $fechaCreacion);

                // Si fue aprobada y luego cerrada por otra asignación, su fecha_resolucion fue fecha_inicio
                if ($estado === 'APROBADA') {
                    $fechaResolucion = $row->fecha_inicio ?? $row->created_at ?? now();
                }

                $asignacionId = DB::table('asignacion')->insertGetId([
                    'ticket_id'            => $row->ticket_id,
                    'estado'               => $estado,
                    'tipo_origen'          => $tipoOrigen,
                    'motivo_solicitud'     => $motivo,
                    'motivo_resolucion'    => $motivoRes,
                    'cargo_origen_codigo'  => $cargoOrigen,
                    'cargo_destino_codigo' => $cargoDestino,
                    'fecha_creacion'       => $fechaCreacion,
                    'fecha_resolucion'     => $fechaResolucion,
                ]);

                // 3. Construir participantes respetando PK (asignacion_id, usuario_id)
                $participants = [];

                if (!empty($row->tecnico_id)) {
                    $participants[(int) $row->tecnico_id] = [
                        'asignacion_id'     => $asignacionId,
                        'usuario_id'        => (int) $row->tecnico_id,
                        'rol_participacion' => 'TECNICO_DESTINO',
                        'fecha_accion'      => $fechaCreacion,
                    ];
                }

                $actorId = $hasRealizadoPor && !empty($row->realizado_por_id) ? (int) $row->realizado_por_id : null;
                if ($actorId) {
                    if ($tipoOrigen === 'REASIGNACION_DIRECTA') {
                        if (isset($participants[$actorId])) {
                            $participants[$actorId]['rol_participacion'] = 'DESTINO_APROBADOR';
                        } else {
                            $participants[$actorId] = [
                                'asignacion_id'     => $asignacionId,
                                'usuario_id'        => $actorId,
                                'rol_participacion' => 'APROBADOR',
                                'fecha_accion'      => $fechaResolucion ?? $fechaCreacion,
                            ];
                        }
                    } else {
                        if (isset($participants[$actorId])) {
                            $participants[$actorId]['rol_participacion'] = 'DESTINO_APROBADOR';
                        } else {
                            $participants[$actorId] = [
                                'asignacion_id'     => $asignacionId,
                                'usuario_id'        => $actorId,
                                'rol_participacion' => 'SOLICITANTE',
                                'fecha_accion'      => $fechaCreacion,
                            ];
                        }
                    }
                }

                // Agregar TECNICO_ORIGEN si existía responsable previo distinto de los ya registrados
                if ($prev && !empty($prev['tecnico_id']) && !isset($participants[(int) $prev['tecnico_id']])) {
                    $participants[(int) $prev['tecnico_id']] = [
                        'asignacion_id'     => $asignacionId,
                        'usuario_id'        => (int) $prev['tecnico_id'],
                        'rol_participacion' => 'TECNICO_ORIGEN',
                        'fecha_accion'      => $fechaCreacion,
                    ];
                }

                if (!empty($participants)) {
                    DB::table('usuario_asignacion')->insert(array_values($participants));
                }

                if ($estado === 'APROBADA' && !empty($row->tecnico_id)) {
                    $prevByTicket[$row->ticket_id] = [
                        'tecnico_id'   => (int) $row->tecnico_id,
                        'cargo_codigo' => $cargoDestino,
                    ];
                }
            }

            Schema::dropIfExists('ticket_asignaciones');
        }
    }

    public function down()
    {
        if (!Schema::hasTable('ticket_asignaciones')) {
            Schema::create('ticket_asignaciones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ticket_id')->constrained('ticket')->cascadeOnDelete();
                $table->foreignId('tecnico_id')->constrained('usuarios')->cascadeOnDelete();
                $table->boolean('activo')->default(false);
                $table->boolean('solicitud_pendiente')->default(false);
                $table->string('tipo_movimiento', 30)->default('ASIGNACION');
                $table->foreignId('realizado_por_id')->nullable()->constrained('usuarios')->nullOnDelete();
                $table->string('cargo_destino_codigo', 30)->nullable();
                $table->text('motivo')->nullable();
                $table->text('motivo_resolucion')->nullable();
                $table->timestampTz('fecha_inicio')->nullable();
                $table->timestampTz('fecha_fin')->nullable();
                $table->timestamps();

                $table->foreign('cargo_destino_codigo')
                    ->references('codigo')->on('cargos')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            });
        }

        Schema::dropIfExists('usuario_asignacion');
        Schema::dropIfExists('asignacion');
    }
}
