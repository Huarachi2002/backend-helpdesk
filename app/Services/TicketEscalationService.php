<?php

namespace App\Services;

use App\Exceptions\TicketEscalationException;
use App\Models\Asignacion;
use App\Models\Cargo;
use App\Models\Configuracion;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
use App\Models\UsuarioAsignacion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Relevamiento de tickets: movimiento VERTICAL entre niveles de la Estructura Organizacional.
 *
 * Reglas (ver "Planificacion - Modulo de Relevamiento.md", §4):
 * - Solo el técnico asignado o un Administrador puede relevar.
 * - El destino lo elige quien releva (nivel + usuario), sin filtros de categoría/prioridad.
 * - Sin saltar niveles, salvo Administradores o `relevamiento_permitir_saltar_niveles = true`.
 * - Es inmediato: el receptor queda como responsable en el acto.
 * - La devolución regresa el ticket a quien lo tenía antes del último relevamiento.
 */
class TicketEscalationService
{
    private const ESTADOS_CERRADOS = ['RESUELTO', 'CERRADO'];

    // ---------------------------------------------------------------------
    // Permisos y estado
    // ---------------------------------------------------------------------

    public function isAdmin(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function canSkipLevels(User $user): bool
    {
        return $this->isAdmin($user)
            || (bool) Configuracion::get('relevamiento_permitir_saltar_niveles', false);
    }

    /**
     * Cargo (nivel) en el que se encuentra actualmente el ticket: el del responsable asignado.
     */
    public function currentCargo(Ticket $ticket): ?Cargo
    {
        $ticket->loadMissing('assignee.cargo');
        return $ticket->assignee?->cargo;
    }

    /**
     * Regla de reasignación (movimiento HORIZONTAL): el nuevo responsable debe estar en el
     * mismo nivel del organigrama donde se encuentra el ticket. Para cambiar de nivel se
     * usa el relevamiento. Devuelve el motivo del rechazo, o null si está permitido.
     */
    public function reassignmentLevelError(Ticket $ticket, User $candidate): ?string
    {
        $actual = $this->currentCargo($ticket);
        if (!$actual) {
            return null; // ticket sin responsable o sin cargo: no hay nivel de referencia
        }

        $candidate->loadMissing('cargo');
        if (!$candidate->cargo || (int) $candidate->cargo->nivel !== (int) $actual->nivel) {
            $nivelDestino = $candidate->cargo ? 'nivel ' . $candidate->cargo->nivel : 'sin cargo';
            return "La reasignación solo puede hacerse dentro del mismo nivel del ticket (nivel {$actual->nivel}). "
                . "'{$candidate->name}' está en {$nivelDestino}. Para cambiar de nivel usa el Relevamiento.";
        }

        return null;
    }

    /**
     * Motivo por el que $user no puede mover verticalmente el ticket (null = permitido).
     */
    public function blockedReason(Ticket $ticket, User $user): ?string
    {
        $ticket->loadMissing(['assignee.cargo', 'solicitudReasignacionPendiente']);

        if (in_array($ticket->estado, self::ESTADOS_CERRADOS, true)) {
            return 'No se puede relevar un ticket resuelto o cerrado.';
        }
        if (!$ticket->assignee) {
            return 'El ticket no tiene un responsable asignado. Usa la reasignación para asignarlo.';
        }
        if (!$this->isAdmin($user) && $ticket->assignee->ci !== $user->ci) {
            return 'Solo el técnico asignado al ticket o un Administrador puede relevarlo.';
        }
        if ($ticket->solicitudReasignacionPendiente) {
            return 'El ticket tiene una solicitud de reasignación pendiente. Resuélvela antes de relevarlo.';
        }
        if (!$ticket->assignee->cargo) {
            return 'El responsable actual no tiene un cargo en la Estructura Organizacional.';
        }

        return null;
    }

    // ---------------------------------------------------------------------
    // Opciones de destino
    // ---------------------------------------------------------------------

    /**
     * Niveles y usuarios a los que $user puede relevar el ticket.
     */
    public function getOptions(Ticket $ticket, User $user): array
    {
        $blocked = $this->blockedReason($ticket, $user);
        $cargoActual = $this->currentCargo($ticket);
        $canSkip = $this->canSkipLevels($user);

        $base = [
            'can_escalate'         => false,
            'blocked_reason'       => $blocked,
            'puede_saltar_niveles' => $canSkip,
            'cargo_actual'         => $cargoActual ? $this->cargoSummary($cargoActual) : null,
            'responsable_actual'   => $ticket->assignee ? $this->userSummary($ticket->assignee) : null,
            'niveles'              => [],
        ];

        if ($blocked) {
            return $base;
        }

        $nivelActual = $cargoActual->nivel;
        $superiores = $cargoActual->codigosSuperiores();
        $subordinados = $cargoActual->codigosSubordinados();

        $usuarios = User::with(['cargo', 'rol'])
            ->whereHas('rol', fn ($q) => $q->whereIn('nombre', User::STAFF_ROLES))
            ->whereHas('cargo', fn ($q) => $q->where('activo', true)->where('nivel', '!=', $nivelActual))
            ->where('activo', true)
            ->where('ci', '!=', $ticket->assignee->ci)
            ->withCount(['assignedTickets as carga_activa' => function ($q) {
                $q->whereNotIn('estado', self::ESTADOS_CERRADOS);
            }])
            ->get()
            ->filter(fn (User $u) => $canSkip || abs($u->cargo->nivel - $nivelActual) === 1);

        $niveles = $usuarios
            ->groupBy(fn (User $u) => $u->cargo->nivel)
            ->map(function (Collection $usuariosNivel, $nivel) use ($nivelActual, $superiores, $subordinados) {
                $ascendente = $nivel < $nivelActual;

                $cargos = $usuariosNivel
                    ->groupBy('cargo_codigo')
                    ->map(function (Collection $usuariosCargo) use ($ascendente, $superiores, $subordinados) {
                        $cargo = $usuariosCargo->first()->cargo;
                        $enLinea = $ascendente
                            ? in_array($cargo->codigo, $superiores, true)
                            : in_array($cargo->codigo, $subordinados, true);

                        return array_merge($this->cargoSummary($cargo), [
                            'en_linea_directa' => $enLinea,
                            'usuarios'         => $usuariosCargo
                                ->sortBy('carga_activa')
                                ->map(fn (User $u) => array_merge($this->userSummary($u), [
                                    'carga_activa' => (int) $u->carga_activa,
                                ]))
                                ->values(),
                        ]);
                    })
                    ->sortBy([['en_linea_directa', 'desc'], ['orden', 'asc']])
                    ->values();

                return [
                    'nivel'     => (int) $nivel,
                    'direccion' => $ascendente ? 'ASCENDENTE' : 'DESCENDENTE',
                    'contiguo'  => abs($nivel - $nivelActual) === 1,
                    'cargos'    => $cargos,
                ];
            })
            ->sortBy('nivel')
            ->values();

        $base['niveles'] = $niveles;
        $base['can_escalate'] = $niveles->isNotEmpty();
        if ($niveles->isEmpty()) {
            $base['blocked_reason'] = $canSkip
                ? 'No hay personal activo en otros niveles de la estructura.'
                : 'No hay personal activo en los niveles contiguos de la estructura.';
        }

        return $base;
    }

    // ---------------------------------------------------------------------
    // Relevamiento
    // ---------------------------------------------------------------------

    /**
     * Releva el ticket a otro nivel (ascendente o descendente) asignándolo a $destinoCi.
     */
    public function escalate(Ticket $ticket, User $actor, string $destinoCi, string $motivo): Asignacion
    {
        if ($reason = $this->blockedReason($ticket, $actor)) {
            throw new TicketEscalationException($reason, 'escalation_blocked');
        }

        $cargoActual = $this->currentCargo($ticket);
        $destino = $this->validDestino($destinoCi);

        if ($destino->ci === $ticket->assignee->ci) {
            throw new TicketEscalationException('El usuario seleccionado ya es el responsable del ticket.', 'same_assignee');
        }

        $nivelDestino = $destino->cargo->nivel;
        if ($nivelDestino === $cargoActual->nivel) {
            throw new TicketEscalationException(
                'El usuario seleccionado está en el mismo nivel. Para moverlo dentro del mismo nivel usa la reasignación.',
                'same_level'
            );
        }

        if (!$this->canSkipLevels($actor) && abs($nivelDestino - $cargoActual->nivel) > 1) {
            throw new TicketEscalationException(
                'El relevamiento debe ser secuencial: solo puedes relevar al nivel inmediato superior o inferior.',
                'skip_levels_not_allowed'
            );
        }

        $tipo = $nivelDestino < $cargoActual->nivel
            ? Asignacion::TIPO_RELEVAMIENTO_ASCENDENTE
            : Asignacion::TIPO_RELEVAMIENTO_DESCENDENTE;

        return $this->moverTicket($ticket, $actor, $destino, $tipo, $motivo);
    }

    // ---------------------------------------------------------------------
    // Devolución
    // ---------------------------------------------------------------------

    /**
     * Información para devolver el ticket al nivel anterior (a quien lo tenía antes del último relevamiento).
     */
    public function getReturnInfo(Ticket $ticket, User $user): array
    {
        $info = ['can_return' => false, 'reason' => null, 'destino' => null, 'cargo' => null, 'alternativas' => []];

        if ($reason = $this->blockedReason($ticket, $user)) {
            $info['reason'] = $reason;
            return $info;
        }

        $activa = $ticket->asignacionActiva()
            ->with(['participantes.usuario.cargo', 'participantes.usuario.rol', 'cargoOrigen'])
            ->first();

        if (!$activa || !in_array($activa->tipo_origen, Asignacion::TIPOS_RELEVAMIENTO, true)) {
            $info['reason'] = 'Solo se puede devolver un ticket que llegó por relevamiento.';
            return $info;
        }

        $tecnico = $activa->tecnicoOrigen();
        $cargo = $activa->cargoOrigen;

        if (!$tecnico) {
            $anterior = $this->asignacionAnterior($activa);
            if ($anterior) {
                $anterior->loadMissing(['participantes.usuario.cargo', 'participantes.usuario.rol', 'cargoDestino']);
                $tecnico = $anterior->tecnicoDestino();
                $cargo = $cargo ?? $anterior->cargoDestino;
            }
        }

        if (!$tecnico && !$cargo) {
            $info['reason'] = 'No se encontró al responsable anterior del ticket.';
            return $info;
        }

        if ($tecnico) {
            $tecnico->loadMissing(['cargo', 'rol']);
        }
        $cargo = $cargo ?? $tecnico?->cargo;
        $info['cargo'] = $cargo ? $this->cargoSummary($cargo) : null;

        $tecnicoValido = $tecnico && $tecnico->activo && $tecnico->isStaff() && $tecnico->cargo;

        if ($tecnicoValido) {
            $info['destino'] = $this->userSummary($tecnico);
        } elseif ($cargo) {
            // El responsable anterior ya no está disponible: se ofrece al personal activo de su cargo.
            $info['alternativas'] = User::where('cargo_codigo', $cargo->codigo)
                ->where('activo', true)
                ->get()
                ->map(fn (User $u) => $this->userSummary($u))
                ->values()
                ->all();
        }

        $info['can_return'] = $tecnicoValido || !empty($info['alternativas']);
        if (!$info['can_return']) {
            $info['reason'] = 'El responsable anterior no está disponible y su cargo no tiene personal activo.';
        }

        return $info;
    }

    /**
     * Devuelve el ticket a quien lo tenía antes del último relevamiento.
     */
    public function returnToPrevious(Ticket $ticket, User $actor, string $instrucciones, ?string $destinoCi = null): Asignacion
    {
        $info = $this->getReturnInfo($ticket, $actor);
        if (!$info['can_return']) {
            throw new TicketEscalationException($info['reason'] ?? 'No se puede devolver el ticket.', 'return_not_allowed');
        }

        if ($info['destino']) {
            $destino = User::with(['cargo', 'rol'])->findOrFail($info['destino']['ci'] ?? $info['destino']['id']);
        } else {
            $permitidos = collect($info['alternativas'])->pluck('id')->all();
            if (!$destinoCi || !in_array($destinoCi, $permitidos, true)) {
                throw new TicketEscalationException(
                    'El responsable anterior ya no está disponible. Selecciona a otra persona de su mismo cargo.',
                    'return_target_required'
                );
            }
            $destino = $this->validDestino($destinoCi);
        }

        return $this->moverTicket($ticket, $actor, $destino, Asignacion::TIPO_DEVOLUCION, $instrucciones);
    }

    // ---------------------------------------------------------------------
    // Núcleo del movimiento
    // ---------------------------------------------------------------------

    private function moverTicket(Ticket $ticket, User $actor, User $destino, string $tipo, string $motivo): Asignacion
    {
        $anterior = $ticket->assignee;
        $cargoOrigen = $anterior?->cargo;
        $cargoDestino = $destino->cargo;

        $movimiento = DB::transaction(function () use ($ticket, $actor, $anterior, $destino, $tipo, $motivo, $cargoOrigen, $cargoDestino) {
            $now = now();

            $movimiento = Asignacion::create([
                'ticket_codigo'        => $ticket->codigo,
                'estado'               => Asignacion::ESTADO_APROBADA,
                'tipo_origen'          => $tipo,
                'motivo_solicitud'     => $motivo,
                'motivo_resolucion'    => null,
                'cargo_origen_codigo'  => $cargoOrigen?->codigo,
                'cargo_destino_codigo' => $cargoDestino->codigo,
                'fecha_creacion'       => $now,
                'fecha_resolucion'     => $now,
            ]);

            // Participantes respetando PK compuesta (asignacion_id, usuario_ci)
            if ($actor->ci === $destino->ci) {
                $movimiento->registrarParticipante($actor->ci, UsuarioAsignacion::ROL_DESTINO_APROBADOR, $now);
            } else {
                $movimiento->registrarParticipante($actor->ci, UsuarioAsignacion::ROL_SOLICITANTE, $now);
                $movimiento->registrarParticipante($destino->ci, UsuarioAsignacion::ROL_TECNICO_DESTINO, $now);
            }

            if ($anterior && $anterior->ci !== $actor->ci && $anterior->ci !== $destino->ci) {
                $movimiento->registrarParticipante($anterior->ci, UsuarioAsignacion::ROL_TECNICO_ORIGEN, $now);
            }

            $ticket->tecnico_asignado_ci = $destino->ci;
            $ticket->save();
            $this->recalcularNivelRelevamiento($ticket, $cargoDestino);

            return $movimiento;
        });

        $etiqueta = [
            Asignacion::TIPO_RELEVAMIENTO_ASCENDENTE  => 'Relevamiento ascendente',
            Asignacion::TIPO_RELEVAMIENTO_DESCENDENTE => 'Relevamiento descendente',
            Asignacion::TIPO_DEVOLUCION               => 'Devolución al nivel anterior',
        ][$tipo];

        $origenTxt = $anterior
            ? "{$anterior->name} (" . ($cargoOrigen?->nombre ?? 'sin cargo') . ($cargoOrigen ? ", Nivel {$cargoOrigen->nivel}" : '') . ')'
            : 'Sin asignar';
        $destinoTxt = "{$destino->name} ({$cargoDestino->nombre}, Nivel {$cargoDestino->nivel})";
        $campoMotivo = $tipo === Asignacion::TIPO_DEVOLUCION ? 'Instrucciones' : 'Motivo';

        TicketHistory::create([
            'ticket_codigo'   => $ticket->codigo,
            'usuario_ci'      => $actor->ci,
            'estado_anterior' => $ticket->status,
            'estado_nuevo'    => $ticket->status,
            'comentarios'     => "{$etiqueta}: {$origenTxt} → {$destinoTxt}. {$campoMotivo}: {$motivo}",
        ]);

        try {
            app(NotificationService::class)->notifyTicketEscalated($ticket, $movimiento, $actor, $anterior, $destino);
        } catch (\Throwable $e) {
            Log::warning("Error al emitir notificaciones de relevamiento: " . $e->getMessage());
        }

        return $movimiento;
    }

    /**
     * Niveles que el ticket está por encima del nivel de su asignación inicial (0 = nivel original).
     */
    public function recalcularNivelRelevamiento(Ticket $ticket, ?Cargo $cargoActual = null): void
    {
        $cargoActual = $cargoActual ?? $this->currentCargo($ticket);

        $inicial = Asignacion::where('ticket_codigo', $ticket->codigo)
            ->where('estado', Asignacion::ESTADO_APROBADA)
            ->orderBy('id')
            ->with(['cargoDestino', 'participantes.usuario.cargo'])
            ->first();
        $cargoInicial = $inicial?->cargoDestino ?? $inicial?->tecnicoDestino()?->cargo;

        $valor = ($cargoActual && $cargoInicial) ? max(0, $cargoInicial->nivel - $cargoActual->nivel) : 0;

        if ((int) $ticket->nivel_relevamiento !== $valor) {
            $ticket->nivel_relevamiento = $valor;
            $ticket->save();
        }
    }

    /**
     * Asignación aprobada inmediatamente anterior a $activa.
     */
    public function asignacionAnterior(Asignacion $activa): ?Asignacion
    {
        return Asignacion::where('ticket_codigo', $activa->ticket_codigo)
            ->where('id', '<', $activa->id)
            ->where('estado', Asignacion::ESTADO_APROBADA)
            ->orderByDesc('id')
            ->first();
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function validDestino(string $destinoCi): User
    {
        $destino = User::with(['cargo', 'rol'])->find($destinoCi);

        if (!$destino || !$destino->activo || !$destino->isStaff()) {
            throw new TicketEscalationException('El usuario seleccionado no es personal activo de la estructura.', 'invalid_target');
        }
        if (!$destino->cargo || !$destino->cargo->activo) {
            throw new TicketEscalationException('El usuario seleccionado no tiene un cargo activo en la estructura.', 'invalid_target');
        }

        return $destino;
    }

    public function cargoSummary(Cargo $cargo): array
    {
        return [
            'codigo'  => $cargo->codigo,
            'nombre'  => $cargo->nombre,
            'seccion' => $cargo->seccion,
            'nivel'   => $cargo->nivel,
            'orden'   => $cargo->orden,
        ];
    }

    public function userSummary(User $user): array
    {
        return [
            'id'    => $user->ci,
            'ci'    => $user->ci,
            'name'  => $user->name,
            'email' => $user->email,
        ];
    }
}
