<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketPrioridad extends Model
{
    use HasFactory;

    protected $table = 'ticket_prioridad';

    protected $primaryKey = 'codigo';
    public $incrementing = false;
    protected $keyType = 'string';

    public const CREATED_AT = 'fecha_creacion';
    public const UPDATED_AT = 'fecha_actualizacion';

    protected $fillable = [
        'codigo',
        'nombre',
        'peso_nivel',
        'sla_horas_resolucion',
        'activo',
    ];

    protected $casts = [
        'peso_nivel'           => 'integer',
        'sla_horas_resolucion' => 'integer',
        'activo'               => 'boolean',
    ];

    protected $appends = ['id'];

    public function getIdAttribute()
    {
        return $this->attributes['codigo'] ?? null;
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'prioridad_codigo', 'codigo');
    }

    public function usuarios()
    {
        return $this->belongsToMany(User::class, 'usuario_prioridad', 'prioridad_codigo', 'usuario_ci', 'codigo', 'ci')
            ->withPivot('asignado_en');
    }
}
