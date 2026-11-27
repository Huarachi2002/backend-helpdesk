<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageVisit extends Model
{
    protected $table = 'visitas_pagina';

    protected $primaryKey = 'ruta';
    public $incrementing = false;
    protected $keyType = 'string';

    public const CREATED_AT = 'fecha_creacion';
    public const UPDATED_AT = 'fecha_actualizacion';

    protected $fillable = [
        'ruta',
        'titulo',
        'visitas',
        'ultimo_usuario_ci',
        'ultima_visita',
    ];

    protected $casts = [
        'visitas' => 'integer',
        'ultima_visita' => 'datetime',
        'fecha_creacion' => 'datetime',
        'fecha_actualizacion' => 'datetime',
    ];

    /**
     * Último usuario autenticado que visitó esta página (relación N:1 hacia usuarios).
     */
    public function ultimoUsuario()
    {
        return $this->belongsTo(User::class, 'ultimo_usuario_ci', 'ci');
    }
}
