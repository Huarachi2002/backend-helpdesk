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
                'id'                   => $this->prioridad->codigo,
                'nombre'               => $this->prioridad->nombre,
                'codigo'               => $this->prioridad->codigo,
                'sla_horas_resolucion' => $this->prioridad->sla_horas_resolucion,
            ] : null),

            'assigned_to' => $this->whenLoaded('assignee', function () {
                if (!$this->assignee) return null;
                $metrics = app(\App\Services\TicketAssignmentService::class)->getAgentMetricsForTicket($this->assignee, $this->resource);
                return [
                    'id'                  => $this->assignee->ci,
                    'ci'                  => $this->assignee->ci,
                    'name'                => $this->assignee->name,
                    'nombre'              => $this->assignee->nombre,
                    'apellido'            => $this->assignee->apellido,
                    'email'               => $this->assignee->email,
                    'correo'              => $this->assignee->correo,
                    'rol'                 => $this->assignee->rol?->nombre,
                    'cargo'               => $this->assignee->cargo ? [
                        'codigo'  => $this->assignee->cargo->codigo,
                        'nombre'  => $this->assignee->cargo->nombre,
                        'seccion' => $this->assignee->cargo->seccion,
                        'nivel'   => $this->assignee->cargo->nivel,
                    ] : null,
                    'asignacion_metricas' => $metrics,
                ];
            }),

            'tecnico_asignado' => $this->whenLoaded('assignee', function () {
                if (!$this->assignee) return null;
                $metrics = app(\App\Services\TicketAssignmentService::class)->getAgentMetricsForTicket($this->assignee, $this->resource);
                return [
                    'id'                  => $this->assignee->ci,
                    'ci'                  => $this->assignee->ci,
                    'name'                => $this->assignee->name,
                    'nombre'              => $this->assignee->nombre,
                    'apellido'            => $this->assignee->apellido,
                    'email'               => $this->assignee->email,
                    'correo'              => $this->assignee->correo,
                    'rol'                 => $this->assignee->rol?->nombre,
                    'cargo'               => $this->assignee->cargo ? [
                        'codigo'  => $this->assignee->cargo->codigo,
                        'nombre'  => $this->assignee->cargo->nombre,
                        'seccion' => $this->assignee->cargo->seccion,
                        'nivel'   => $this->assignee->cargo->nivel,
                    ] : null,
                    'asignacion_metricas' => $metrics,
                ];
            }),

            'created_by' => $this->whenLoaded('creator', fn() => $this->creator ? [
                'id'                => $this->creator->ci,
                'ci'                => $this->creator->ci,
                'name'              => $this->creator->name,
                'nombre'            => $this->creator->nombre,
                'apellido'          => $this->creator->apellido,
                'email'             => $this->creator->email,
                'correo'            => $this->creator->correo,
                'moodle_user_id'    => $this->creator->moodle_usuario_id,
                'moodle_usuario_id' => $this->creator->moodle_usuario_id,
                'rol'               => $this->creator->rol?->nombre ?? 'ESTUDIANTE',
            ] : null),

            'solicitante' => $this->whenLoaded('creator', fn() => $this->creator ? [
                'id'                => $this->creator->ci,
                'ci'                => $this->creator->ci,
                'name'              => $this->creator->name,
                'nombre'            => $this->creator->nombre,
                'apellido'          => $this->creator->apellido,
                'email'             => $this->creator->email,
                'correo'            => $this->creator->correo,
                'moodle_user_id'    => $this->creator->moodle_usuario_id,
                'moodle_usuario_id' => $this->creator->moodle_usuario_id,
                'rol'               => $this->creator->rol?->nombre ?? 'ESTUDIANTE',
            ] : null),

            'history' => $this->whenLoaded('history', fn() =>
                $this->history->map(fn($h) => [
                    'id'           => $h->id,
                    'old_status'   => $h->estado_anterior,
                    'new_status'   => $h->estado_nuevo,
                    'comments'     => $h->comentarios,
                    'changed_by'   => $h->changer ? $h->changer->name : null,
                    'changer_role' => $h->changer ? $h->changer->role : null,
                    'changed_at'   => $h->fecha_creacion?->toDateTimeString(),
                ])
            ),

            'mensajes' => $this->whenLoaded('mensajes', fn() =>
                TicketMessageResource::collection($this->mensajes)
            ),

            'pending_reassignment' => $this->solicitudReasignacionPendiente ? (function () use ($request) {
                $pending = $this->solicitudReasignacionPendiente;
                $pending->loadMissing('participantes.usuario.rol');
                $solicitante = $pending->solicitante() ?? $this->assignee;
                $propuesto = $pending->tecnicoDestino();
                $anterior = $pending->tecnicoOrigen() ?? $this->assignee;

                return [
                    'id'                   => $pending->id,
                    'motivo'               => $pending->motivo_solicitud,
                    'motivo_solicitud'     => $pending->motivo_solicitud,
                    'estado'               => $pending->estado,
                    'tipo_origen'          => $pending->tipo_origen,
                    // Aprobación jerárquica (D5): quién puede resolverla y si el usuario actual puede
                    'can_resolve'          => $request->user()
                        ? app(\App\Services\ReassignmentApprovalService::class)->canResolve($request->user(), $pending, $this->resource)
                        : false,
                    'aprobadores'          => app(\App\Services\ReassignmentApprovalService::class)->approversSummary(
                        app(\App\Services\ReassignmentApprovalService::class)->approvers($pending, $this->resource)
                    ),
                    'fecha_solicitud'      => $pending->fecha_creacion?->toDateTimeString(),
                    'solicitante'          => $solicitante ? [
                        'id'    => $solicitante->id,
                        'name'  => $solicitante->name,
                        'email' => $solicitante->email,
                        'rol'   => $solicitante->rol?->nombre,
                    ] : null,
                    'tecnico_propuesto'    => $propuesto ? [
                        'id'    => $propuesto->id,
                        'name'  => $propuesto->name,
                        'email' => $propuesto->email,
                        'rol'   => $propuesto->rol?->nombre,
                    ] : null,
                    'tecnico_anterior'     => $anterior ? [
                        'id'    => $anterior->id,
                        'name'  => $anterior->name,
                        'email' => $anterior->email,
                    ] : null,
                ];
            })() : null,

            'assignment_history' => $this->whenLoaded('asignaciones', function () {
                $activaId = $this->asignaciones
                    ->firstWhere('estado', \App\Models\Asignacion::ESTADO_APROBADA)?->id;

                return $this->asignaciones->map(function ($a) use ($activaId) {
                    $destino = $a->tecnicoDestino();
                    $origen = $a->tecnicoOrigen();
                    $solicitante = $a->solicitante();
                    $aprobador = $a->aprobador();
                    $rechazador = $a->rechazador();

                    return [
                        'id'                  => $a->id,
                        'estado'              => $a->estado,
                        'tipo_origen'         => $a->tipo_origen,
                        'tipo_movimiento'     => $a->tipo_origen,
                        'activo'              => $a->id === $activaId,
                        'solicitud_pendiente' => $a->estado === \App\Models\Asignacion::ESTADO_PENDIENTE,
                        'tecnico_id'          => $destino?->id,
                        'tecnico_name'        => $destino?->name,
                        'motivo'              => $a->motivo_solicitud,
                        'motivo_solicitud'    => $a->motivo_solicitud,
                        'motivo_resolucion'   => $a->motivo_resolucion,
                        'fecha_creacion'      => $a->fecha_creacion?->toDateTimeString(),
                        'fecha_resolucion'    => $a->fecha_resolucion?->toDateTimeString(),
                        'fecha_inicio'        => $a->fecha_creacion?->toDateTimeString(),
                        'fecha_fin'           => $a->fecha_resolucion?->toDateTimeString(),
                        'realizado_por'       => ($solicitante ?? $aprobador ?? $rechazador)?->name,
                        'participantes'       => [
                            'solicitante'     => $solicitante ? ['id' => $solicitante->id, 'name' => $solicitante->name] : null,
                            'tecnico_origen'  => $origen ? ['id' => $origen->id, 'name' => $origen->name] : null,
                            'tecnico_destino' => $destino ? ['id' => $destino->id, 'name' => $destino->name] : null,
                            'aprobador'       => $aprobador ? ['id' => $aprobador->id, 'name' => $aprobador->name] : null,
                            'rechazador'      => $rechazador ? ['id' => $rechazador->id, 'name' => $rechazador->name] : null,
                        ],
                        'cargo_origen'        => $a->cargo_origen_codigo ? [
                            'codigo' => $a->cargo_origen_codigo,
                            'nombre' => $a->cargoOrigen?->nombre,
                            'nivel'  => $a->cargoOrigen?->nivel,
                        ] : null,
                        'cargo_destino'       => $a->cargo_destino_codigo ? [
                            'codigo' => $a->cargo_destino_codigo,
                            'nombre' => $a->cargoDestino?->nombre,
                            'nivel'  => $a->cargoDestino?->nivel,
                        ] : null,
                    ];
                });
            }),

            // Relevamiento: nivel actual del ticket en la Estructura Organizacional
            'nivel_relevamiento' => (int) ($this->nivel_relevamiento ?? 0),
            'cargo_actual'       => ($this->relationLoaded('assignee') && $this->assignee && $this->assignee->relationLoaded('cargo') && $this->assignee->cargo) ? [
                'codigo'  => $this->assignee->cargo->codigo,
                'nombre'  => $this->assignee->cargo->nombre,
                'seccion' => $this->assignee->cargo->seccion,
                'nivel'   => $this->assignee->cargo->nivel,
            ] : null,

            'escalation' => $this->whenLoaded('asignaciones', function () use ($request) {
                $service = app(\App\Services\TicketEscalationService::class);
                $user = $request->user();
                $activa = $this->asignaciones->firstWhere('estado', \App\Models\Asignacion::ESTADO_APROBADA);
                $tiposVerticales = \App\Models\Asignacion::TIPOS_VERTICALES;

                $blocked = $user ? $service->blockedReason($this->resource, $user) : 'Usuario no autenticado.';
                $return = $user ? $service->getReturnInfo($this->resource, $user) : ['can_return' => false, 'reason' => null, 'destino' => null];

                return [
                    'can_escalate'       => $blocked === null,
                    'blocked_reason'     => $blocked,
                    'can_return'         => (bool) $return['can_return'],
                    'return_reason'      => $return['reason'],
                    'return_destino'     => $return['destino'],
                    'ultimo_movimiento'  => ($activa && in_array($activa->tipo_origen, $tiposVerticales, true)) ? [
                        'id'            => $activa->id,
                        'tipo'          => $activa->tipo_origen,
                        'realizado_por' => ($activa->solicitante() ?? $activa->aprobador())?->name,
                        'motivo'        => $activa->motivo_solicitud,
                        'fecha'         => $activa->fecha_creacion?->toDateTimeString(),
                        'cargo_destino' => $activa->cargoDestino ? [
                            'codigo' => $activa->cargoDestino->codigo,
                            'nombre' => $activa->cargoDestino->nombre,
                            'nivel'  => $activa->cargoDestino->nivel,
                        ] : null,
                    ] : null,
                ];
            }),

            'reassignment_history' => $this->whenLoaded('asignaciones', function () {
                $activaId = $this->asignaciones
                    ->firstWhere('estado', \App\Models\Asignacion::ESTADO_APROBADA)?->id;

                return $this->asignaciones->map(function ($r) use ($activaId) {
                    $solicitante = $r->solicitante() ?? $r->tecnicoOrigen();
                    $destino = $r->tecnicoDestino();
                    $resolutor = $r->resolutor();

                    return [
                        'id'                  => $r->id,
                        'estado'              => $r->estado,
                        'tipo_origen'         => $r->tipo_origen,
                        'activo'              => $r->id === $activaId,
                        'solicitud_pendiente' => $r->estado === \App\Models\Asignacion::ESTADO_PENDIENTE,
                        'motivo'              => $r->motivo_solicitud,
                        'motivo_solicitud'    => $r->motivo_solicitud,
                        'motivo_resolucion'   => $r->motivo_resolucion,
                        'fecha_solicitud'     => $r->fecha_creacion?->toDateTimeString(),
                        'fecha_resolucion'    => $r->fecha_resolucion?->toDateTimeString(),
                        'solicitante'         => $solicitante?->name,
                        'tecnico_propuesto'   => $destino?->name,
                        'revisado_por'        => $resolutor?->name,
                        'aprobador'           => $r->aprobador()?->name,
                        'rechazador'          => $r->rechazador()?->name,
                    ];
                });
            }),

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
