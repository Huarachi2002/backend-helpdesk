<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BotInteraction extends Model
{
    use HasFactory;

    protected $table = 'interacciones_bot';

    public const CREATED_AT = 'fecha_creacion';
    public const UPDATED_AT = 'fecha_actualizacion';

    protected $fillable = [
        'usuario_ci',
        'intencion',
        'consulta',
        'respuesta',
        'resuelto',
        'tiempo_resolucion_segundos',
        'fecha_creacion',
        // Compatibilidad
        'user_id',
        'intent',
        'query',
        'response',
        'resolved',
        'resolution_time_seconds',
        'created_at',
    ];

    protected $casts = [
        'resuelto'                   => 'boolean',
        'tiempo_resolucion_segundos' => 'integer',
        'fecha_creacion'             => 'datetime',
        'fecha_actualizacion'        => 'datetime',
    ];

    protected $appends = [
        'user_id',
        'intent',
        'query',
        'response',
        'resolved',
        'resolution_time_seconds',
        'created_at',
    ];

    // --- Accesores y Mutadores de Compatibilidad ---

    public function getUserIdAttribute() { return $this->attributes['usuario_ci'] ?? null; }
    public function setUserIdAttribute($v) { $this->attributes['usuario_ci'] = $v; }

    public function getIntentAttribute() { return $this->attributes['intencion'] ?? null; }
    public function setIntentAttribute($v) { $this->attributes['intencion'] = $v; }

    public function getQueryAttribute() { return $this->attributes['consulta'] ?? null; }
    public function setQueryAttribute($v) { $this->attributes['consulta'] = $v; }

    public function getResponseAttribute() { return $this->attributes['respuesta'] ?? null; }
    public function setResponseAttribute($v) { $this->attributes['respuesta'] = $v; }

    public function getResolvedAttribute() { return isset($this->attributes['resuelto']) ? (bool) $this->attributes['resuelto'] : false; }
    public function setResolvedAttribute($v) { $this->attributes['resuelto'] = (bool) $v; }

    public function getResolutionTimeSecondsAttribute() { return $this->attributes['tiempo_resolucion_segundos'] ?? 0; }
    public function setResolutionTimeSecondsAttribute($v) { $this->attributes['tiempo_resolucion_segundos'] = (int) $v; }

    public function getCreatedAtAttribute() { return $this->fecha_creacion; }
    public function setCreatedAtAttribute($v) { $this->attributes['fecha_creacion'] = $v; }

    // --- Relaciones ---

    public function user()
    {
        return $this->belongsTo(User::class, 'usuario_ci', 'ci');
    }
}
