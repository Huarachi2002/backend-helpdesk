<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TicketMessageResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                 => $this->id,
            'ticket_id'          => $this->ticket_id,
            'tipo_emisor'        => $this->tipo_emisor,
            'canal_origen'       => $this->canal_origen,
            'remitente_nombre'   => $this->remitente_nombre,
            'remitente_email'    => $this->remitente_email,
            'cuerpo_mensaje'     => $this->cuerpo_mensaje,
            'enviado_por_correo' => !empty($this->correo_message_id),
            'usuario'            => $this->whenLoaded('usuario', fn () => $this->usuario ? [
                'id'   => $this->usuario->id,
                'name' => $this->usuario->name,
                'rol'  => $this->usuario->rol?->nombre,
            ] : null),
            'fecha_creacion'     => $this->fecha_creacion?->toDateTimeString(),
        ];
    }
}
