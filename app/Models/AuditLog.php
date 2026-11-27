<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'bitacora_accesos';
    public $timestamps = false;

    protected $fillable = [
        'usuario_ci',
        'tipo_evento',
        'ip',
        'recurso',
        'metodo',
        'descripcion',
        'agente_usuario',
        'correo_intento',
        'fecha_creacion',
    ];

    protected $casts = [
        'fecha_creacion' => 'datetime',
    ];

    protected $appends = ['usuario_id', 'user_agent', 'created_at'];

    public function getUsuarioIdAttribute()
    {
        return $this->attributes['usuario_ci'] ?? null;
    }

    public function getUserAgentAttribute()
    {
        return $this->attributes['agente_usuario'] ?? null;
    }

    public function getCreatedAtAttribute()
    {
        return $this->fecha_creacion;
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_ci', 'ci');
    }

    public static function registrar(string $evento, ?string $userCi = null, array $extra = []): void
    {
        try {
            static::create([
                'usuario_ci'     => $userCi,
                'tipo_evento'    => $evento,
                'ip'             => request()->ip(),
                'recurso'        => request()->path(),
                'metodo'         => request()->method(),
                'agente_usuario' => request()->userAgent(),
                'descripcion'    => $extra['descripcion'] ?? null,
                'correo_intento' => $extra['correo_intento'] ?? null,
                'fecha_creacion' => now(),
            ]);
        } catch (\Throwable $e) {
            // No interrumpir el flujo si falla el log
        }
    }
}
