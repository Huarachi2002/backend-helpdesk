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
        'usuario_id',
        'tipo',
        'titulo',
        'mensaje',
        'ticket_id',
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

    // Constantes de tipos de notificación
    public const TIPO_TICKET_CREADO          = 'TICKET_CREADO';
    public const TIPO_CAMBIO_ESTADO          = 'CAMBIO_ESTADO';
    public const TIPO_RECORDATORIO_PENDIENTE = 'RECORDATORIO_PENDIENTE';
    public const TIPO_SOLICITUD_REASIGNACION = 'SOLICITUD_REASIGNACION';
    public const TIPO_REASIGNACION_APROBADA  = 'REASIGNACION_APROBADA';
    public const TIPO_REASIGNACION_RECHAZADA = 'REASIGNACION_RECHAZADA';
    public const TIPO_TICKET_SIN_RESPUESTA   = 'TICKET_SIN_RESPUESTA';

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function scopeUnread($query)
    {
        return $query->where('leida', false);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('usuario_id', $userId);
    }
}
