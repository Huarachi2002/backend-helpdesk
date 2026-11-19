<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketAsignacion extends Model
{
    use HasFactory;

    protected $table = 'ticket_asignaciones';

    protected $fillable = [
        'ticket_id',
        'tecnico_id',
        'activo',
        'solicitud_pendiente',
        'motivo',
        'motivo_resolucion',
        'fecha_inicio',
        'fecha_fin',
    ];

    protected $casts = [
        'activo'              => 'boolean',
        'solicitud_pendiente' => 'boolean',
        'fecha_inicio'        => 'datetime',
        'fecha_fin'           => 'datetime',
    ];

    /**
     * Ticket al que pertenece la asignación.
     */
    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    /**
     * Única relación a usuarios: Técnico asignado (o propuesto si es solicitud).
     */
    public function tecnico()
    {
        return $this->belongsTo(User::class, 'tecnico_id');
    }

    /**
     * Scope para obtener la asignación activa actual.
     */
    public function scopeActiva($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope para obtener solicitudes pendientes de autorización por el Director.
     */
    public function scopePendiente($query)
    {
        return $query->where('solicitud_pendiente', true);
    }

    /**
     * Scope para obtener el historial pasado de asignaciones.
     */
    public function scopeHistorico($query)
    {
        return $query->where('activo', false)->where('solicitud_pendiente', false);
    }
}
