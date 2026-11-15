<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketPrioridad extends Model
{
    use HasFactory;

    protected $table = 'ticket_prioridad';

    public const CREATED_AT = 'fecha_creacion';
    public const UPDATED_AT = 'fecha_actualizacion';

    protected $fillable = [
        'nombre',
        'codigo',
        'peso_nivel',
        'sla_horas_resolucion',
        'activo',
    ];

    protected $casts = [
        'peso_nivel'           => 'integer',
        'sla_horas_resolucion' => 'integer',
        'activo'               => 'boolean',
    ];

    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'prioridad_id');
    }

    public function usuarios()
    {
        return $this->belongsToMany(User::class, 'usuario_prioridad', 'prioridad_id', 'usuario_id')
            ->withPivot('asignado_en');
    }
}
