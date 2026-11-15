<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketMensaje extends Model
{
    use HasFactory;

    protected $table = 'ticket_mensaje';

    public const CREATED_AT = 'fecha_creacion';
    public const UPDATED_AT = null;

    protected $fillable = [
        'ticket_id',
        'usuario_id',
        'tipo_emisor',
        'canal_origen',
        'remitente_email',
        'remitente_nombre',
        'cuerpo_mensaje',
        'correo_message_id',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function adjuntos()
    {
        return $this->hasMany(TicketAdjunto::class, 'mensaje_id');
    }
}
