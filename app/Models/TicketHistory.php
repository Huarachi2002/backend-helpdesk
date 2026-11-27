<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketHistory extends Model
{
    use HasFactory;

    protected $table = 'ticket_historial';

    public const CREATED_AT = 'fecha_creacion';
    public const UPDATED_AT = 'fecha_actualizacion';

    protected $fillable = [
        'ticket_codigo',
        'usuario_ci',
        'estado_anterior',
        'estado_nuevo',
        'comentarios',
        'fecha_creacion',
        'fecha_actualizacion',
        // Compatibilidad en código legado
        'ticket_id',
        'changed_by',
        'old_status',
        'new_status',
        'comments',
        'created_at',
    ];

    protected $casts = [
        'fecha_creacion'      => 'datetime',
        'fecha_actualizacion' => 'datetime',
    ];

    // --- Accesores y Mutadores de Compatibilidad ---

    public function getTicketIdAttribute() { return $this->attributes['ticket_codigo'] ?? null; }
    public function setTicketIdAttribute($v) { $this->attributes['ticket_codigo'] = $v; }

    public function getChangedByAttribute() { return $this->attributes['usuario_ci'] ?? null; }
    public function setChangedByAttribute($v) { $this->attributes['usuario_ci'] = $v; }

    public function getOldStatusAttribute() { return $this->attributes['estado_anterior'] ?? null; }
    public function setOldStatusAttribute($v) { $this->attributes['estado_anterior'] = $v; }

    public function getNewStatusAttribute() { return $this->attributes['estado_nuevo'] ?? null; }
    public function setNewStatusAttribute($v) { $this->attributes['estado_nuevo'] = $v; }

    public function getCommentsAttribute() { return $this->attributes['comentarios'] ?? null; }
    public function setCommentsAttribute($v) { $this->attributes['comentarios'] = $v; }

    public function getCreatedAtAttribute() { return $this->fecha_creacion; }
    public function setCreatedAtAttribute($v) { $this->attributes['fecha_creacion'] = $v; }

    // --- Relaciones ---

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_codigo', 'codigo');
    }

    public function changer()
    {
        return $this->belongsTo(User::class, 'usuario_ci', 'ci');
    }
}
