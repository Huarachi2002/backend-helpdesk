<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory;

    protected $table = 'ticket';

    public const CREATED_AT = 'fecha_creacion';
    public const UPDATED_AT = 'fecha_actualizacion';

    protected $fillable = [
        'codigo',
        'solicitante_id',
        'tecnico_asignado_id',
        'prioridad_id',
        'asunto',
        'descripcion',
        'estado',
        'correo_hilo',
        'fecha_respuesta',
        'fecha_resuelto',
        'fecha_cierre',

        // Atributos de compatibilidad
        'title',
        'description',
        'status',
        'priority',
        'category_id',
        'assigned_to',
        'created_by',
        'first_response_at',
        'resolved_at',
        'moodle_user_id',
        'moodle_course',
        'moodle_role',
    ];

    protected $casts = [
        'fecha_creacion'    => 'datetime',
        'fecha_respuesta'   => 'datetime',
        'fecha_resuelto'    => 'datetime',
        'fecha_cierre'      => 'datetime',
        'fecha_actualizacion' => 'datetime',
    ];

    /**
     * Mapeo bidireccional entre códigos internos del State Pattern y la BD.
     */
    protected static array $statusToEstado = [
        'open'        => 'EN_ESPERA',
        'in_progress' => 'CLIENTE_ESPERANDO_RESPUESTA',
        'waiting'     => 'ESPERANDO_POR_CLIENTE',
        'resolved'    => 'RESUELTO',
        'closed'      => 'CERRADO',
    ];

    protected static array $estadoToStatus = [
        'EN_ESPERA'                    => 'open',
        'CLIENTE_ESPERANDO_RESPUESTA'  => 'in_progress',
        'ESPERANDO_POR_CLIENTE'        => 'waiting',
        'RESUELTO'                     => 'resolved',
        'CERRADO'                      => 'closed',
    ];

    // --- Accesores y Mutadores de Compatibilidad ---

    public function getTitleAttribute()
    {
        return $this->attributes['asunto'] ?? null;
    }

    public function setTitleAttribute($value)
    {
        $this->attributes['asunto'] = $value;
    }

    public function getDescriptionAttribute()
    {
        return $this->attributes['descripcion'] ?? null;
    }

    public function setDescriptionAttribute($value)
    {
        $this->attributes['descripcion'] = $value;
    }

    public function getStatusAttribute()
    {
        $raw = $this->attributes['estado'] ?? 'EN_ESPERA';
        return self::$estadoToStatus[$raw] ?? strtolower($raw);
    }

    public function setStatusAttribute($value)
    {
        $this->attributes['estado'] = self::$statusToEstado[$value] ?? strtoupper($value);
    }

    public function getPriorityAttribute()
    {
        if ($this->relationLoaded('prioridad') && $this->prioridad) {
            return strtolower($this->prioridad->codigo);
        }
        if (!empty($this->attributes['prioridad_id'])) {
            $p = TicketPrioridad::find($this->attributes['prioridad_id']);
            return $p ? strtolower($p->codigo) : 'medium';
        }
        return 'medium';
    }

    public function setPriorityAttribute($value)
    {
        $p = TicketPrioridad::whereRaw('LOWER(codigo) = ?', [strtolower($value)])->first();
        if ($p) {
            $this->attributes['prioridad_id'] = $p->id;
        }
    }

    public function getCategoryIdAttribute()
    {
        return $this->categorias()->first()?->id ?? null;
    }

    public function getCategoryAttribute()
    {
        return $this->categorias->first();
    }

    public function getAssignedToAttribute()
    {
        return $this->attributes['tecnico_asignado_id'] ?? null;
    }

    public function setAssignedToAttribute($value)
    {
        $this->attributes['tecnico_asignado_id'] = $value;
    }

    public function getCreatedByAttribute()
    {
        return $this->attributes['solicitante_id'] ?? null;
    }

    public function setCreatedByAttribute($value)
    {
        $this->attributes['solicitante_id'] = $value;
    }

    public function getFirstResponseAtAttribute()
    {
        return $this->fecha_respuesta;
    }

    public function setFirstResponseAtAttribute($value)
    {
        $this->attributes['fecha_respuesta'] = $value;
    }

    public function getResolvedAtAttribute()
    {
        return $this->fecha_resuelto;
    }

    public function setResolvedAtAttribute($value)
    {
        $this->attributes['fecha_resuelto'] = $value;
    }

    public function getCreatedAtAttribute()
    {
        return $this->fecha_creacion;
    }

    public function getUpdatedAtAttribute()
    {
        return $this->fecha_actualizacion;
    }

    public function getMoodleUserIdAttribute()
    {
        return $this->creator?->moodle_user_id ? 'moodle_std_' . $this->creator->moodle_user_id : 'moodle_std_1042';
    }

    public function getMoodleCourseAttribute()
    {
        return 'Campus Virtual Moodle';
    }

    public function getMoodleRoleAttribute()
    {
        return $this->creator?->rol?->nombre ?? 'ESTUDIANTE';
    }

    // --- Relaciones ---

    public function categorias()
    {
        return $this->belongsToMany(Categoria::class, 'ticket_categoria', 'ticket_id', 'categoria_id');
    }

    public function prioridad()
    {
        return $this->belongsTo(TicketPrioridad::class, 'prioridad_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'tecnico_asignado_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'solicitante_id');
    }

    public function history()
    {
        return $this->hasMany(TicketHistory::class, 'ticket_id')->orderBy('created_at', 'asc')->orderBy('id', 'asc');
    }

    public function mensajes()
    {
        return $this->hasMany(TicketMensaje::class, 'ticket_id')->orderBy('fecha_creacion', 'asc');
    }

    // --- State Pattern ---

    public function state(): \App\States\Ticket\TicketState
    {
        return \App\States\Ticket\TicketStateFactory::make($this);
    }

    public function transitionTo(string $targetStatus, ?User $user = null, ?string $comments = null): Ticket
    {
        return $this->state()->transitionTo($targetStatus, $user, $comments);
    }

    public function canTransitionTo(string $targetStatus): bool
    {
        return $this->state()->canTransitionTo($targetStatus);
    }

    public function allowedTransitions(): array
    {
        return $this->state()->allowedTransitions();
    }
}
