<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TicketMessageResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                  => $this->id,
            'ticket_id'           => $this->ticket_codigo,
            'ticket_codigo'       => $this->ticket_codigo,
            'tipo_emisor'         => $this->tipo_emisor,
            'canal_origen'        => $this->canal_origen,
            'remitente_nombre'    => $this->remitente_nombre,
            'remitente_email'     => $this->remitente_correo,
            'remitente_correo'    => $this->remitente_correo,
            'cuerpo_mensaje'      => $this->cuerpo_mensaje,
            // Respuesta escrita en la web que salió por correo a la contraparte.
            'enviado_por_correo'  => $this->canal_origen !== 'EMAIL' && !empty($this->correo_mensaje_id),
            // Respuesta que el usuario envió desde su bandeja de correo.
            'recibido_por_correo' => $this->canal_origen === 'EMAIL',
            'usuario'             => $this->whenLoaded('usuario', fn () => $this->usuario ? [
                'id'   => $this->usuario->ci,
                'ci'   => $this->usuario->ci,
                'name' => $this->usuario->name,
                'rol'  => $this->usuario->rol?->nombre,
            ] : null),
            'fecha_creacion'      => $this->fecha_creacion?->toDateTimeString(),
        ];
    }
}
