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

            'assigned_to' => $this->whenLoaded('assignee', function () {
                if (!$this->assignee) return null;
                $metrics = app(\App\Services\TicketAssignmentService::class)->getAgentMetricsForTicket($this->assignee, $this->resource);
                return [
                    'id'                  => $this->assignee->id,
                    'name'                => $this->assignee->name,
                    'nombre'              => $this->assignee->nombre,
                    'apellido'            => $this->assignee->apellido,
                    'email'               => $this->assignee->email,
                    'correo'              => $this->assignee->correo,
                    'rol'                 => $this->assignee->rol?->nombre,
                    'asignacion_metricas' => $metrics,
                ];
            }),

            'tecnico_asignado' => $this->whenLoaded('assignee', function () {
                if (!$this->assignee) return null;
                $metrics = app(\App\Services\TicketAssignmentService::class)->getAgentMetricsForTicket($this->assignee, $this->resource);
                return [
                    'id'                  => $this->assignee->id,
                    'name'                => $this->assignee->name,
                    'nombre'              => $this->assignee->nombre,
                    'apellido'            => $this->assignee->apellido,
                    'email'               => $this->assignee->email,
                    'correo'              => $this->assignee->correo,
                    'rol'                 => $this->assignee->rol?->nombre,
                    'asignacion_metricas' => $metrics,
                ];
            }),

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

            'mensajes' => $this->whenLoaded('mensajes', fn() =>
                TicketMessageResource::collection($this->mensajes)
            ),

            'pending_reassignment' => $this->solicitudReasignacionPendiente ? [
                'id'                   => $this->solicitudReasignacionPendiente->id,
                'motivo'               => $this->solicitudReasignacionPendiente->motivo,
                'estado'               => 'PENDIENTE',
                'fecha_solicitud'      => ($this->solicitudReasignacionPendiente->fecha_inicio ?? $this->solicitudReasignacionPendiente->created_at)?->toDateTimeString(),
                'solicitante'          => $this->assignee ? [
                    'id'       => $this->assignee->id,
                    'name'     => $this->assignee->name,
                    'email'    => $this->assignee->email,
                    'rol'      => $this->assignee->rol?->nombre,
                ] : null,
                'tecnico_propuesto'    => $this->solicitudReasignacionPendiente->tecnico ? [
                    'id'       => $this->solicitudReasignacionPendiente->tecnico->id,
                    'name'     => $this->solicitudReasignacionPendiente->tecnico->name,
                    'email'    => $this->solicitudReasignacionPendiente->tecnico->email,
                    'rol'      => $this->solicitudReasignacionPendiente->tecnico->rol?->nombre,
                ] : null,
                'tecnico_anterior'     => $this->assignee ? [
                    'id'       => $this->assignee->id,
                    'name'     => $this->assignee->name,
                    'email'    => $this->assignee->email,
                ] : null,
            ] : null,

            'assignment_history' => $this->whenLoaded('asignaciones', fn() =>
                $this->asignaciones->map(fn($a) => [
                    'id'                  => $a->id,
                    'tecnico_id'          => $a->tecnico_id,
                    'tecnico_name'        => $a->tecnico?->name,
                    'activo'              => (bool) $a->activo,
                    'solicitud_pendiente' => (bool) $a->solicitud_pendiente,
                    'motivo'              => $a->motivo,
                    'motivo_resolucion'   => $a->motivo_resolucion,
                    'fecha_inicio'        => $a->fecha_inicio?->toDateTimeString(),
                    'fecha_fin'           => $a->fecha_fin?->toDateTimeString(),
                ])
            ),

            'reassignment_history' => $this->whenLoaded('asignaciones', fn() =>
                $this->asignaciones->map(fn($r) => [
                    'id'                  => $r->id,
                    'motivo'              => $r->motivo,
                    'estado'              => $r->activo ? 'ACTIVO' : ($r->solicitud_pendiente ? 'PENDIENTE' : 'HISTORICO'),
                    'activo'              => (bool) $r->activo,
                    'solicitud_pendiente' => (bool) $r->solicitud_pendiente,
                    'motivo_resolucion'   => $r->motivo_resolucion,
                    'fecha_solicitud'     => $r->fecha_inicio?->toDateTimeString(),
                    'fecha_resolucion'    => $r->fecha_fin?->toDateTimeString(),
                    'solicitante'         => $this->assignee?->name,
                    'tecnico_propuesto'   => $r->tecnico?->name,
                    'revisado_por'        => 'Dirección',
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
