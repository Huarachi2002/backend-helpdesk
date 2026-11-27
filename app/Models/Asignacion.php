<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Operación de asignación, reasignación, relevamiento o devolución de un ticket.
 * Sus participantes se almacenan en la tabla intermedia `usuario_asignacion` (PK: asignacion_id, usuario_ci).
 */
class Asignacion extends Model
{
    use HasFactory;

    public const ESTADO_PENDIENTE = 'PENDIENTE';
    public const ESTADO_APROBADA  = 'APROBADA';
    public const ESTADO_RECHAZADA = 'RECHAZADA';

    public const TIPO_AUTOMATICA               = 'AUTOMATICA';
    public const TIPO_SOLICITUD_REASIGNACION   = 'SOLICITUD_REASIGNACION';
    public const TIPO_REASIGNACION_DIRECTA     = 'REASIGNACION_DIRECTA';
    public const TIPO_RELEVAMIENTO_ASCENDENTE  = 'RELEVAMIENTO_ASCENDENTE';
    public const TIPO_RELEVAMIENTO_DESCENDENTE = 'RELEVAMIENTO_DESCENDENTE';
    public const TIPO_DEVOLUCION               = 'DEVOLUCION';

    public const TIPOS_RELEVAMIENTO = [
        self::TIPO_RELEVAMIENTO_ASCENDENTE,
        self::TIPO_RELEVAMIENTO_DESCENDENTE,
    ];

    public const TIPOS_VERTICALES = [
        self::TIPO_RELEVAMIENTO_ASCENDENTE,
        self::TIPO_RELEVAMIENTO_DESCENDENTE,
        self::TIPO_DEVOLUCION,
    ];

    protected $table = 'asignacion';

    public const CREATED_AT = 'fecha_creacion';
    public const UPDATED_AT = null;

    protected $fillable = [
        'ticket_codigo',
        'estado',
        'tipo_origen',
        'motivo_solicitud',
        'motivo_resolucion',
        'cargo_origen_codigo',
        'cargo_destino_codigo',
        'fecha_creacion',
        'fecha_resolucion',
    ];

    protected $casts = [
        'fecha_creacion'   => 'datetime',
        'fecha_resolucion' => 'datetime',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_codigo', 'codigo');
    }

    public function participantes()
    {
        return $this->hasMany(UsuarioAsignacion::class, 'asignacion_id');
    }

    public function usuarios()
    {
        return $this->belongsToMany(User::class, 'usuario_asignacion', 'asignacion_id', 'usuario_ci', 'id', 'ci')
            ->using(UsuarioAsignacion::class)
            ->withPivot(['rol_participacion', 'fecha_accion']);
    }

    public function cargoOrigen()
    {
        return $this->belongsTo(Cargo::class, 'cargo_origen_codigo', 'codigo');
    }

    public function cargoDestino()
    {
        return $this->belongsTo(Cargo::class, 'cargo_destino_codigo', 'codigo');
    }

    /**
     * Inserta o actualiza la participación de un usuario respetando PK (asignacion_id, usuario_ci).
     */
    public function registrarParticipante(string $usuarioCi, string $rol, $fecha = null): void
    {
        DB::table('usuario_asignacion')->updateOrInsert(
            [
                'asignacion_id' => $this->id,
                'usuario_ci'    => $usuarioCi,
            ],
            [
                'rol_participacion' => $rol,
                'fecha_accion'      => $fecha ?? now(),
            ]
        );

        $this->unsetRelation('participantes');
        $this->unsetRelation('usuarios');
    }

    public function participacionPorRoles(array $roles): ?UsuarioAsignacion
    {
        $lista = $this->relationLoaded('participantes')
            ? $this->participantes
            : $this->participantes()->with('usuario.rol', 'usuario.cargo')->get();

        return $lista->first(fn (UsuarioAsignacion $p) => in_array($p->rol_participacion, $roles, true));
    }

    public function solicitante(): ?User
    {
        return $this->participacionPorRoles([UsuarioAsignacion::ROL_SOLICITANTE])?->usuario;
    }

    public function tecnicoDestino(): ?User
    {
        return $this->participacionPorRoles([
            UsuarioAsignacion::ROL_TECNICO_DESTINO,
            UsuarioAsignacion::ROL_DESTINO_APROBADOR,
            UsuarioAsignacion::ROL_DESTINO_RECHAZADOR,
        ])?->usuario;
    }

    public function tecnicoDestinoCi(): ?string
    {
        return $this->participacionPorRoles([
            UsuarioAsignacion::ROL_TECNICO_DESTINO,
            UsuarioAsignacion::ROL_DESTINO_APROBADOR,
            UsuarioAsignacion::ROL_DESTINO_RECHAZADOR,
        ])?->usuario_ci;
    }

    public function tecnicoOrigen(): ?User
    {
        $explicit = $this->participacionPorRoles([UsuarioAsignacion::ROL_TECNICO_ORIGEN]);
        if ($explicit) {
            return $explicit->usuario;
        }

        if (in_array($this->tipo_origen, array_merge([self::TIPO_SOLICITUD_REASIGNACION], self::TIPOS_VERTICALES), true)) {
            return $this->solicitante();
        }

        return null;
    }

    public function aprobador(): ?User
    {
        return $this->participacionPorRoles([
            UsuarioAsignacion::ROL_APROBADOR,
            UsuarioAsignacion::ROL_DESTINO_APROBADOR,
        ])?->usuario;
    }

    public function rechazador(): ?User
    {
        return $this->participacionPorRoles([
            UsuarioAsignacion::ROL_RECHAZADOR,
            UsuarioAsignacion::ROL_DESTINO_RECHAZADOR,
        ])?->usuario;
    }

    public function resolutor(): ?User
    {
        return $this->aprobador() ?? $this->rechazador();
    }
}
