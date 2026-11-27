<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Ticket extends Model
{
    use HasFactory;

    protected $table = 'ticket';

    protected $primaryKey = 'codigo';
    public $incrementing = false;
    protected $keyType = 'string';

    public const CREATED_AT = 'fecha_creacion';
    public const UPDATED_AT = 'fecha_actualizacion';

    protected $fillable = [
        'codigo',
        'solicitante_ci',
        'tecnico_asignado_ci',
        'prioridad_codigo',
        'asunto',
        'descripcion',
        'estado',
        'correo_hilo',
        'fecha_respuesta',
        'fecha_resuelto',
        'fecha_cierre',
        'nivel_relevamiento',

        // Atributos de compatibilidad
        'title',
        'description',
        'status',
        'priority',
        'assigned_to',
        'created_by',
        'first_response_at',
        'resolved_at',
    ];

    protected $casts = [
        'fecha_creacion'      => 'datetime',
        'fecha_respuesta'     => 'datetime',
        'fecha_resuelto'      => 'datetime',
        'fecha_cierre'        => 'datetime',
        'fecha_actualizacion' => 'datetime',
        'nivel_relevamiento'  => 'integer',
    ];

    protected $appends = ['id'];

    /**
     * Genera el siguiente código único correlativo de ticket (ej: TCK-026) usando secuencia atómica.
     */
    public static function generarSiguienteCodigo(): string
    {
        if (DB::getDriverName() === 'pgsql') {
            $next = (int) DB::selectOne("SELECT nextval('ticket_codigo_seq') AS val")->val;
        } else {
            $max = static::query()->count() + 1;
            $next = $max;
        }

        return 'TCK-' . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

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

    public function getIdAttribute()
    {
        return $this->attributes['codigo'] ?? null;
    }

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
        if (!empty($this->attributes['prioridad_codigo'])) {
            return strtolower($this->attributes['prioridad_codigo']);
        }
        if ($this->relationLoaded('prioridad') && $this->prioridad) {
            return strtolower($this->prioridad->codigo);
        }
        return 'medium';
    }

    public function setPriorityAttribute($value)
    {
        $p = TicketPrioridad::whereRaw('LOWER(codigo) = ?', [strtolower($value)])->first();
        if ($p) {
            $this->attributes['prioridad_codigo'] = $p->codigo;
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
        return $this->attributes['tecnico_asignado_ci'] ?? null;
    }

    public function setAssignedToAttribute($value)
    {
        $this->attributes['tecnico_asignado_ci'] = $value;
    }

    public function getCreatedByAttribute()
    {
        return $this->attributes['solicitante_ci'] ?? null;
    }

    public function setCreatedByAttribute($value)
    {
        $this->attributes['solicitante_ci'] = $value;
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
        return $this->creator?->moodle_usuario_id ? 'moodle_std_' . $this->creator->moodle_usuario_id : 'moodle_std_1042';
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
        return $this->belongsToMany(Categoria::class, 'ticket_categoria', 'ticket_codigo', 'categoria_id', 'codigo', 'id');
    }

    public function prioridad()
    {
        return $this->belongsTo(TicketPrioridad::class, 'prioridad_codigo', 'codigo');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'tecnico_asignado_ci', 'ci');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'solicitante_ci', 'ci');
    }

    public function history()
    {
        return $this->hasMany(TicketHistory::class, 'ticket_codigo', 'codigo')
            ->orderBy('fecha_creacion', 'asc')
            ->orderBy('id', 'asc');
    }

    public function mensajes()
    {
        return $this->hasMany(TicketMensaje::class, 'ticket_codigo', 'codigo')
            ->orderBy('fecha_creacion', 'asc');
    }

    public function asignaciones()
    {
        return $this->hasMany(Asignacion::class, 'ticket_codigo', 'codigo')
            ->orderBy('fecha_creacion', 'desc')
            ->orderBy('id', 'desc');
    }

    public function asignacionActiva()
    {
        return $this->hasOne(Asignacion::class, 'ticket_codigo', 'codigo')
            ->ofMany(['id' => 'max'], function ($query) {
                $query->where('estado', Asignacion::ESTADO_APROBADA);
            });
    }

    public function solicitudReasignacionPendiente()
    {
        return $this->hasOne(Asignacion::class, 'ticket_codigo', 'codigo')
            ->ofMany(['id' => 'max'], function ($query) {
                $query->where('estado', Asignacion::ESTADO_PENDIENTE);
            });
    }

    public function reasignaciones()
    {
        return $this->asignaciones();
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
