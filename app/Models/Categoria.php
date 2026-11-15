<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Categoria extends Model
{
    use HasFactory;

    protected $table = 'categorias';

    public const CREATED_AT = 'fecha_creacion';
    public const UPDATED_AT = 'fecha_actualizacion';

    protected $fillable = [
        'nombre',
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    // Accessors for backward compatibility
    public function getNameAttribute()
    {
        return $this->nombre;
    }

    public function setNameAttribute($value)
    {
        $this->attributes['nombre'] = $value;
    }

    public function getDescriptionAttribute()
    {
        return $this->descripcion;
    }

    public function setDescriptionAttribute($value)
    {
        $this->attributes['descripcion'] = $value;
    }

    public function tickets()
    {
        return $this->belongsToMany(Ticket::class, 'ticket_categoria', 'categoria_id', 'ticket_id');
    }

    public function usuarios()
    {
        return $this->belongsToMany(User::class, 'usuario_categoria', 'categoria_id', 'usuario_id')
            ->withPivot('asignado_en');
    }
}
