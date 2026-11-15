<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $cat = $this->categorias->first() ?? $this->category;

        return [
            'id'                  => $this->id,
            'codigo'              => $this->codigo,
            'asunto'              => $this->asunto,
            'descripcion'         => $this->descripcion,
            'estado'              => $this->estado,
            'title'               => $this->title,
            'description'         => $this->description,
            'status'              => $this->status,
            'status_label'        => $this->state()->label(),
            'allowed_transitions' => $this->allowedTransitions(),
            'priority'            => $this->priority,
            'correo_hilo'         => $this->correo_hilo,

            'moodle_data' => [
                'user_id' => $this->moodle_user_id,
                'course'  => $this->moodle_course,
                'role'    => $this->moodle_role,
            ],

            'category' => $cat ? [
                'id'   => $cat->id,
                'name' => $cat->nombre ?? $cat->name,
            ] : null,

            'categorias' => $this->whenLoaded('categorias', fn() =>
                $this->categorias->map(fn($c) => [
                    'id'     => $c->id,
                    'nombre' => $c->nombre,
                ])
            ),

            'prioridad' => $this->whenLoaded('prioridad', fn() => $this->prioridad ? [
                'id'                   => $this->prioridad->id,
                'nombre'               => $this->prioridad->nombre,
                'codigo'               => $this->prioridad->codigo,
                'sla_horas_resolucion' => $this->prioridad->sla_horas_resolucion,
            ] : null),

            'assigned_to' => $this->whenLoaded('assignee', fn() => $this->assignee ? [
                'id'       => $this->assignee->id,
                'name'     => $this->assignee->name,
                'nombre'   => $this->assignee->nombre,
                'apellido' => $this->assignee->apellido,
                'email'    => $this->assignee->email,
                'correo'   => $this->assignee->correo,
                'rol'      => $this->assignee->rol?->nombre,
            ] : null),

            'tecnico_asignado' => $this->whenLoaded('assignee', fn() => $this->assignee ? [
                'id'       => $this->assignee->id,
                'name'     => $this->assignee->name,
                'nombre'   => $this->assignee->nombre,
                'apellido' => $this->assignee->apellido,
                'email'    => $this->assignee->email,
                'correo'   => $this->assignee->correo,
                'rol'      => $this->assignee->rol?->nombre,
            ] : null),

            'created_by' => $this->whenLoaded('creator', fn() => $this->creator ? [
                'id'             => $this->creator->id,
                'name'           => $this->creator->name,
                'nombre'         => $this->creator->nombre,
                'apellido'       => $this->creator->apellido,
                'email'          => $this->creator->email,
                'correo'         => $this->creator->correo,
                'moodle_user_id' => $this->creator->moodle_user_id,
                'rol'            => $this->creator->rol?->nombre ?? 'ESTUDIANTE',
            ] : null),

            'solicitante' => $this->whenLoaded('creator', fn() => $this->creator ? [
                'id'             => $this->creator->id,
                'name'           => $this->creator->name,
                'nombre'         => $this->creator->nombre,
                'apellido'       => $this->creator->apellido,
                'email'          => $this->creator->email,
                'correo'         => $this->creator->correo,
                'moodle_user_id' => $this->creator->moodle_user_id,
                'rol'            => $this->creator->rol?->nombre ?? 'ESTUDIANTE',
            ] : null),

            'history' => $this->whenLoaded('history', fn() =>
                $this->history->map(fn($h) => [
                    'id'           => $h->id,
                    'old_status'   => $h->old_status,
                    'new_status'   => $h->new_status,
                    'comments'     => $h->comments,
                    'changed_by'   => $h->changer ? $h->changer->name : null,
                    'changer_role' => $h->changer ? $h->changer->role : null,
                    'changed_at'   => $h->created_at->toDateTimeString(),
                ])
            ),

            'times' => [
                'first_response_at' => $this->first_response_at?->toDateTimeString(),
                'resolved_at'       => $this->resolved_at?->toDateTimeString(),
                'created_at'        => ($this->created_at ?? $this->fecha_creacion)?->toDateTimeString(),
                'updated_at'        => ($this->updated_at ?? $this->fecha_actualizacion)?->toDateTimeString(),
            ],

            'fecha_creacion'    => $this->fecha_creacion?->toDateTimeString(),
            'fecha_respuesta'   => $this->fecha_respuesta?->toDateTimeString(),
            'fecha_resuelto'    => $this->fecha_resuelto?->toDateTimeString(),
            'fecha_cierre'      => $this->fecha_cierre?->toDateTimeString(),
            'fecha_actualizacion' => $this->fecha_actualizacion?->toDateTimeString(),
        ];
    }
}
