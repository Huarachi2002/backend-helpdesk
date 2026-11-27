<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Tabla intermedia pura `usuario_asignacion`.
 * Clave primaria compuesta: (asignacion_id, usuario_ci).
 */
class UsuarioAsignacion extends Pivot
{
    public const ROL_SOLICITANTE      = 'SOLICITANTE';
    public const ROL_TECNICO_ORIGEN   = 'TECNICO_ORIGEN';
    public const ROL_TECNICO_DESTINO  = 'TECNICO_DESTINO';
    public const ROL_APROBADOR        = 'APROBADOR';
    public const ROL_RECHAZADOR       = 'RECHAZADOR';

    public const ROL_DESTINO_APROBADOR  = 'DESTINO_APROBADOR';
    public const ROL_DESTINO_RECHAZADOR = 'DESTINO_RECHAZADOR';

    protected $table = 'usuario_asignacion';

    public $incrementing = false;
    protected $primaryKey = null;
    public $timestamps = false;

    protected $fillable = [
        'asignacion_id',
        'usuario_ci',
        'rol_participacion',
        'fecha_accion',
    ];

    protected $casts = [
        'asignacion_id' => 'integer',
        'fecha_accion'  => 'datetime',
    ];

    public function asignacion()
    {
        return $this->belongsTo(Asignacion::class, 'asignacion_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_ci', 'ci');
    }
}
