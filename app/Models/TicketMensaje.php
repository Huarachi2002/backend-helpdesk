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
        'ticket_codigo',
        'usuario_ci',
        'tipo_emisor',
        'canal_origen',
        'remitente_correo',
        'remitente_nombre',
        'cuerpo_mensaje',
        'correo_mensaje_id',
        // Compatibilidad
        'ticket_id',
        'usuario_id',
        'remitente_email',
        'correo_message_id',
    ];

    protected $casts = [
        'fecha_creacion' => 'datetime',
    ];

    public function getTicketIdAttribute() { return $this->attributes['ticket_codigo'] ?? null; }
    public function setTicketIdAttribute($v) { $this->attributes['ticket_codigo'] = $v; }

    public function getUsuarioIdAttribute() { return $this->attributes['usuario_ci'] ?? null; }
    public function setUsuarioIdAttribute($v) { $this->attributes['usuario_ci'] = $v; }

    public function getRemitenteEmailAttribute() { return $this->attributes['remitente_correo'] ?? null; }
    public function setRemitenteEmailAttribute($v) { $this->attributes['remitente_correo'] = $v; }

    public function getCorreoMessageIdAttribute() { return $this->attributes['correo_mensaje_id'] ?? null; }
    public function setCorreoMessageIdAttribute($v) { $this->attributes['correo_mensaje_id'] = $v; }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_codigo', 'codigo');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_ci', 'ci');
    }

    public function adjuntos()
    {
        return $this->hasMany(TicketAdjunto::class, 'mensaje_id');
    }
}
