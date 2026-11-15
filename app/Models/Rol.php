<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rol extends Model
{
    use HasFactory;

    protected $table = 'roles';

    public const CREATED_AT = 'fecha_creacion';
    public const UPDATED_AT = 'fecha_actualizacion';

    protected $fillable = [
        'nombre',
        'descripcion',
        'es_interno',
    ];

    protected $casts = [
        'es_interno' => 'boolean',
    ];

    public function usuarios()
    {
        return $this->hasMany(User::class, 'rol_id');
    }
}
