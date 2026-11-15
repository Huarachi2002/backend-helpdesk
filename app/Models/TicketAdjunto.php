<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketAdjunto extends Model
{
    use HasFactory;

    protected $table = 'ticket_adjuntos';

    public const CREATED_AT = 'fecha_creacion';
    public const UPDATED_AT = null;

    protected $fillable = [
        'mensaje_id',
        'nombre_original',
        'extension',
        'nombre_almacenado',
        'ruta_almacenado',
    ];

    public function mensaje()
    {
        return $this->belongsTo(TicketMensaje::class, 'mensaje_id');
    }
}
