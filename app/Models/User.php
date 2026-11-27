<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'usuarios';

    protected $primaryKey = 'ci';
    public $incrementing = false;
    protected $keyType = 'string';

    public const CREATED_AT = 'fecha_creacion';
    public const UPDATED_AT = 'fecha_actualizacion';

    protected $fillable = [
        'ci',
        'rol_id',
        'cargo_codigo',
        'nombre',
        'apellido',
        'correo',
        'contrasenia',
        'sesion',
        'moodle_usuario_id',
        'activo',
        // Campos virtuales de compatibilidad
        'moodle_user_id',
        'name',
        'email',
        'password',
        'role',
        'department',
    ];

    protected $hidden = [
        'contrasenia',
        'remember_token',
    ];

    protected $casts = [
        'sesion' => 'boolean',
        'activo' => 'boolean',
    ];

    protected $appends = [
        'id',
        'name',
        'email',
        'role',
        'support_agent',
    ];

    /**
     * Password para autenticación de Laravel / Sanctum.
     */
    public function getAuthPassword()
    {
        return $this->contrasenia;
    }

    // --- Accesores y Mutadores de Compatibilidad ---

    public function getIdAttribute()
    {
        return $this->attributes['ci'] ?? null;
    }

    public function getMoodleUserIdAttribute()
    {
        return $this->attributes['moodle_usuario_id'] ?? null;
    }

    public function setMoodleUserIdAttribute($value)
    {
        $this->attributes['moodle_usuario_id'] = $value;
    }

    public function getNameAttribute()
    {
        $fullName = trim(($this->attributes['nombre'] ?? '') . ' ' . ($this->attributes['apellido'] ?? ''));
        return $fullName ?: ($this->attributes['nombre'] ?? '');
    }

    public function setNameAttribute($value)
    {
        $parts = explode(' ', trim($value), 2);
        $this->attributes['nombre'] = $parts[0] ?? $value;
        $this->attributes['apellido'] = $parts[1] ?? null;
    }

    public function getEmailAttribute()
    {
        return $this->attributes['correo'] ?? null;
    }

    public function setEmailAttribute($value)
    {
        $this->attributes['correo'] = $value;
    }

    public function setPasswordAttribute($value)
    {
        $this->attributes['contrasenia'] = $value;
    }

    public function getRoleAttribute()
    {
        $rolNombre = strtoupper($this->rol?->nombre ?? '');
        if ($rolNombre === 'DIRECTOR') return 'admin';
        if ($rolNombre === 'TECNICO') return 'support';
        if ($rolNombre === 'DOCENTE') return 'teacher';
        if ($rolNombre === 'ESTUDIANTE') return 'estudiante';
        return strtolower($rolNombre) ?: 'estudiante';
    }

    public function setRoleAttribute($value)
    {
        $val = strtoupper($value);
        if ($val === 'ADMIN') $val = 'DIRECTOR';
        if ($val === 'SUPPORT') $val = 'TECNICO';
        if ($val === 'TEACHER') $val = 'DOCENTE';

        $rol = Rol::where('nombre', $val)->first();
        if ($rol) {
            $this->attributes['rol_id'] = $rol->id;
        }
    }

    public function getDepartmentAttribute()
    {
        return $this->attributes['department'] ?? ($this->rol?->nombre ?? null);
    }

    public function getSupportAgentAttribute()
    {
        $cats = $this->relationLoaded('categorias') ? $this->categorias : $this->categorias()->get();
        $priors = $this->relationLoaded('prioridades') ? $this->prioridades : $this->prioridades()->get();

        return [
            'is_active'          => (bool) $this->activo,
            'tier_level'         => 2,
            'max_active_tickets' => 10,
            'allowed_priorities' => $priors->pluck('codigo')->map(fn($c) => strtolower($c))->toArray(),
            'categories'         => $cats->map(fn($c) => [
                'id'   => $c->id,
                'name' => $c->nombre,
            ])->toArray(),
        ];
    }

    // --- Relaciones ---

    public function rol()
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }

    /**
     * Cargo que ocupa el usuario en la estructura organizacional (solo personal).
     */
    public function cargo()
    {
        return $this->belongsTo(Cargo::class, 'cargo_codigo', 'codigo');
    }

    /**
     * Indica si el usuario forma parte del personal (Director o Técnico) y por lo tanto
     * debe ocupar un cargo en la estructura organizacional.
     */
    public function isStaff(): bool
    {
        return in_array(strtoupper($this->rol?->nombre ?? ''), self::STAFF_ROLES, true);
    }

    /** Roles de sistema que forman parte del organigrama. */
    public const STAFF_ROLES = ['DIRECTOR', 'TECNICO'];

    public function categorias()
    {
        return $this->belongsToMany(Categoria::class, 'usuario_categoria', 'usuario_ci', 'categoria_id', 'ci', 'id')
            ->withPivot('asignado_en');
    }

    public function prioridades()
    {
        return $this->belongsToMany(TicketPrioridad::class, 'usuario_prioridad', 'usuario_ci', 'prioridad_codigo', 'ci', 'codigo')
            ->withPivot('asignado_en');
    }

    public function createdTickets()
    {
        return $this->hasMany(Ticket::class, 'solicitante_ci', 'ci');
    }

    public function assignedTickets()
    {
        return $this->hasMany(Ticket::class, 'tecnico_asignado_ci', 'ci');
    }

    public function asignaciones()
    {
        return $this->belongsToMany(Asignacion::class, 'usuario_asignacion', 'usuario_ci', 'asignacion_id', 'ci', 'id')
            ->using(UsuarioAsignacion::class)
            ->withPivot(['rol_participacion', 'fecha_accion']);
    }

    public function botInteractions()
    {
        return $this->hasMany(BotInteraction::class, 'usuario_ci', 'ci');
    }

    public function configuraciones()
    {
        return $this->hasMany(Configuracion::class, 'usuario_ci', 'ci');
    }

    /**
     * Páginas en las que este usuario fue el último visitante registrado (relación 1:N).
     */
    public function visitasPagina()
    {
        return $this->hasMany(PageVisit::class, 'ultimo_usuario_ci', 'ci');
    }
}
