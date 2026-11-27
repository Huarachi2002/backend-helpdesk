<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notificacion extends Model
{
    use HasFactory;

    protected $table = 'notificaciones';

    public const CREATED_AT = 'fecha_creacion';
    public const UPDATED_AT = 'fecha_actualizacion';

    protected $fillable = [
        'usuario_ci',
        'tipo',
        'titulo',
        'mensaje',
        'ticket_codigo',
        'datos',
        'leida',
        'fecha_leida',
    ];

    protected $casts = [
        'datos'               => 'array',
        'leida'               => 'boolean',
        'fecha_leida'         => 'datetime',
        'fecha_creacion'      => 'datetime',
        'fecha_actualizacion' => 'datetime',
    ];

    protected $appends = ['usuario_id', 'ticket_id'];

    // Constantes de tipos de notificación
    public const TIPO_TICKET_CREADO          = 'TICKET_CREADO';
    public const TIPO_CAMBIO_ESTADO          = 'CAMBIO_ESTADO';
    public const TIPO_RECORDATORIO_PENDIENTE = 'RECORDATORIO_PENDIENTE';
    public const TIPO_SOLICITUD_REASIGNACION = 'SOLICITUD_REASIGNACION';
    public const TIPO_REASIGNACION_APROBADA  = 'REASIGNACION_APROBADA';
    public const TIPO_REASIGNACION_RECHAZADA = 'REASIGNACION_RECHAZADA';
    public const TIPO_TICKET_SIN_RESPUESTA   = 'TICKET_SIN_RESPUESTA';
    public const TIPO_RESPUESTA_TICKET       = 'RESPUESTA_TICKET';
    public const TIPO_TICKET_RELEVADO        = 'TICKET_RELEVADO';
    public const TIPO_TICKET_DEVUELTO        = 'TICKET_DEVUELTO';

    public function getUsuarioIdAttribute()
    {
        return $this->attributes['usuario_ci'] ?? null;
    }

    public function getTicketIdAttribute()
    {
        return $this->attributes['ticket_codigo'] ?? null;
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_ci', 'ci');
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_codigo', 'codigo');
    }

    public function scopeUnread($query)
    {
        return $query->where('leida', false);
    }

    public function scopeForUser($query, string $userCi)
    {
        return $query->where('usuario_ci', $userCi);
    }
}
