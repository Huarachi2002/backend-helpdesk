<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TicketAssignmentService
{
    /**
     * Matriz de ponderación adaptativa según la prioridad del ticket.
     * Basado exclusivamente en los 4 criterios de asignación acordados:
     * 1. Carga actual de trabajo (workload)
     * 2. Especialidad / Experiencia en la categoría (category)
     * 3. Tasa de cumplimiento de SLA (sla)
     * 4. Velocidad promedio de resolución MTTR (mttr)
     *
     * Los pesos suman exactamente 1.0 (100%) para cada nivel de prioridad.
     */
    protected array $weightsByPriority = [
        'critical' => [
            'workload' => 0.20,
            'category' => 0.15,
            'sla'      => 0.35,
            'mttr'     => 0.30,
        ],
        'high' => [
            'workload' => 0.25,
            'category' => 0.25,
            'sla'      => 0.30,
            'mttr'     => 0.20,
        ],
        'medium' => [
            'workload' => 0.30,
            'category' => 0.30,
            'sla'      => 0.25,
            'mttr'     => 0.15,
        ],
        'low' => [
            'workload' => 0.25,
            'category' => 0.45,
            'sla'      => 0.15,
            'mttr'     => 0.15,
        ],
    ];

    /**
     * Asigna automáticamente un ticket al técnico más idóneo utilizando
     * scoring multi-criterio ponderado sobre los 4 algoritmos:
     * 1. Carga actual de trabajo (tickets activos en cola)
     * 2. Experiencia específica en la categoría
     * 3. Tasa histórica de cumplimiento de SLA
     * 4. Velocidad promedio de resolución (MTTR en horas)
     */
    public function assign(Ticket $ticket): ?User
    {
        // 1. Obtener técnicos elegibles que cumplan los filtros binarios (categoría + prioridad)
        $eligibleAgents = $this->getEligibleAgents($ticket);

        // 2. Si no hay técnicos con restricciones coincidentes, fallback a todos los técnicos activos
        $candidates = $eligibleAgents->isNotEmpty() ? $eligibleAgents : $this->getFallbackCandidates();

        if ($candidates->isEmpty()) {
            $ticket->tecnico_asignado_ci = null;
            $ticket->save();
            return null;
        }

        // 3. Evaluar y puntuar candidatos con los 4 algoritmos de asignación
        $scoredCandidates = $this->scoreCandidates($candidates, $ticket);

        // 4. Seleccionar el candidato con mayor puntaje
        $chosen = $scoredCandidates->first();

        if ($chosen) {
            $ticket->tecnico_asignado_ci = $chosen->ci;
            $ticket->save();
            return $chosen;
        }

        $ticket->tecnico_asignado_ci = null;
        $ticket->save();
        return null;
    }

    /**
     * Obtiene todos los técnicos elegibles para un ticket ordenados por su puntaje multi-criterio.
     */
    public function getEligibleAgentsWithScores(Ticket $ticket): Collection
    {
        $eligible = $this->getEligibleAgents($ticket);
        if ($eligible->isEmpty()) {
            return collect();
        }
        return $this->scoreCandidates($eligible, $ticket);
    }

    /**
     * Obtiene los candidatos que cumplen las reglas de elegibilidad binarias.
     */
    public function getEligibleAgents(Ticket $ticket): Collection
    {
        $candidates = User::whereHas('rol', function ($q) {
            $q->whereIn('nombre', ['TECNICO', 'DIRECTOR']);
        })
        ->where('activo', true)
        ->with(['categorias', 'prioridades', 'rol'])
        ->withCount(['assignedTickets' => function ($query) {
            $query->whereNotIn('estado', ['RESUELTO', 'CERRADO']);
        }])
        ->get();

        return $candidates->filter(function ($user) use ($ticket) {
            $validation = $this->validateEligibility($user, $ticket);
            return $validation['eligible'];
        })->values();
    }

    /**
     * Obtiene candidatos de reserva (cualquier técnico o director activo) en caso de que
     * ningún técnico esté explícitamente parametrizado para la categoría/prioridad.
     */
    public function getFallbackCandidates(): Collection
    {
        return User::whereHas('rol', function ($q) {
            $q->whereIn('nombre', ['TECNICO', 'DIRECTOR']);
        })
        ->where('activo', true)
        ->with(['categorias', 'prioridades', 'rol'])
        ->withCount(['assignedTickets' => function ($query) {
            $query->whereNotIn('estado', ['RESUELTO', 'CERRADO']);
        }])
        ->get();
    }

    /**
     * Evalúa a un conjunto de candidatos y les asigna un puntaje compuesto de 0 a 100
     * utilizando los 4 algoritmos de asignación.
     * Retorna la colección ordenada descendentemente por assignment_score.
     */
    public function scoreCandidates(Collection $candidates, Ticket $ticket): Collection
    {
        if ($candidates->isEmpty()) {
            return collect();
        }

        $candidateCis = $candidates->pluck('ci')->toArray();
        $ticketCatIds = $ticket->categorias->pluck('id')->toArray();
        if (empty($ticketCatIds) && $ticket->category_id) {
            $ticketCatIds = [$ticket->category_id];
        }

        // Determinar ponderación según prioridad del ticket
        $priorityCode = strtolower($ticket->priority ?: 'medium');
        $weights = $this->weightsByPriority[$priorityCode] ?? $this->weightsByPriority['medium'];

        // --- Algoritmo 1: Carga actual de trabajo (tickets activos en curso) ---
        $workloads = DB::table('ticket')
            ->select('tecnico_asignado_ci', DB::raw('COUNT(*) as total'))
            ->whereIn('tecnico_asignado_ci', $candidateCis)
            ->whereNotIn('estado', ['RESUELTO', 'CERRADO'])
            ->groupBy('tecnico_asignado_ci')
            ->pluck('total', 'tecnico_asignado_ci')
            ->toArray();

        // --- Algoritmo 2: Experiencia / Especialidad en la categoría ---
        $catExp = [];
        if (!empty($ticketCatIds)) {
            $catExp = DB::table('ticket')
                ->join('ticket_categoria', 'ticket.codigo', '=', 'ticket_categoria.ticket_codigo')
                ->whereIn('ticket.tecnico_asignado_ci', $candidateCis)
                ->whereIn('ticket_categoria.categoria_id', $ticketCatIds)
                ->whereIn('ticket.estado', ['RESUELTO', 'CERRADO'])
                ->select('ticket.tecnico_asignado_ci', DB::raw('COUNT(*) as total'))
                ->groupBy('ticket.tecnico_asignado_ci')
                ->pluck('total', 'tecnico_asignado_ci')
                ->toArray();
        }

        // --- Algoritmos 3 & 4: Cumplimiento de SLA y Velocidad MTTR (horas de resolución) ---
        $slaAndMttr = DB::table('ticket')
            ->leftJoin('ticket_prioridad', 'ticket.prioridad_codigo', '=', 'ticket_prioridad.codigo')
            ->whereIn('ticket.tecnico_asignado_ci', $candidateCis)
            ->whereIn('ticket.estado', ['RESUELTO', 'CERRADO'])
            ->whereNotNull('ticket.fecha_resuelto')
            ->select(
                'ticket.tecnico_asignado_ci',
                DB::raw('COUNT(*) as total_resueltos'),
                DB::raw('AVG(EXTRACT(EPOCH FROM (ticket.fecha_resuelto - ticket.fecha_creacion))/3600) as mttr_horas'),
                DB::raw("SUM(CASE WHEN (EXTRACT(EPOCH FROM (ticket.fecha_resuelto - ticket.fecha_creacion))/3600) <= COALESCE(ticket_prioridad.sla_horas_resolucion, 48) THEN 1 ELSE 0 END) as dentro_sla")
            )
            ->groupBy('ticket.tecnico_asignado_ci')
            ->get()
            ->keyBy('tecnico_asignado_ci');

        // Parámetros de normalización relativa entre la muestra de candidatos
        $maxCatExp = !empty($catExp) ? max($catExp) : 0;

        $mttrList = [];
        foreach ($slaAndMttr as $row) {
            if ($row->total_resueltos > 0 && $row->mttr_horas !== null) {
                $mttrList[] = (float) $row->mttr_horas;
            }
        }
        $minMttr = !empty($mttrList) ? min($mttrList) : null;
        $maxMttr = !empty($mttrList) ? max($mttrList) : null;

        // Calcular puntajes normalizados (0-100) para cada técnico
        $scored = $candidates->map(function ($agent) use (
            $workloads, $catExp, $slaAndMttr,
            $maxCatExp, $minMttr, $maxMttr, $weights
        ) {
            $cid = $agent->ci;

            // 1. Criterio Carga: Escala lineal sobre capacidad sugerida de 10 tickets
            $load = $workloads[$cid] ?? 0;
            $agent->assigned_tickets_count = $load;
            $sWorkload = max(0.0, min(100.0, 100.0 * (1.0 - ($load / 10.0))));

            // 2. Criterio Experiencia en Categoría:
            $exp = $catExp[$cid] ?? 0;
            if ($maxCatExp <= 0) {
                $sCat = 50.0; // Neutro si nadie tiene historial en esta categoría
            } else {
                $sCat = 20.0 + 80.0 * ($exp / $maxCatExp);
            }

            // 3. Criterio Cumplimiento SLA:
            $perf = $slaAndMttr[$cid] ?? null;
            $totalResueltos = $perf?->total_resueltos ?? 0;
            $dentroSla = $perf?->dentro_sla ?? 0;
            if ($totalResueltos == 0) {
                $sSla = 50.0; // Neutro para técnicos nuevos sin historial
                $slaRate = 100.0;
            } else {
                $slaRate = round(($dentroSla / $totalResueltos) * 100, 1);
                $sSla = 100.0 * ($dentroSla / $totalResueltos);
            }

            // 4. Criterio Velocidad de Resolución (MTTR):
            $rawMttr = $perf?->mttr_horas !== null ? round((float) $perf->mttr_horas, 1) : 0.0;
            if ($totalResueltos == 0 || $minMttr === null || $maxMttr === null) {
                $sMttr = 50.0; // Neutro
            } elseif ($minMttr == $maxMttr) {
                $sMttr = 75.0;
            } else {
                $techMttr = (float) $perf->mttr_horas;
                $sMttr = 30.0 + 70.0 * (1.0 - (($techMttr - $minMttr) / ($maxMttr - $minMttr)));
            }

            // Puntaje global ponderado (0 - 100) sobre los 4 criterios
            $totalScore = ($sWorkload * $weights['workload'])
                        + ($sCat * $weights['category'])
                        + ($sSla * $weights['sla'])
                        + ($sMttr * $weights['mttr']);

            $totalScore = round($totalScore, 2);

            $breakdown = [
                'workload' => round($sWorkload, 1),
                'category' => round($sCat, 1),
                'sla'      => round($sSla, 1),
                'mttr'     => round($sMttr, 1),
            ];

            $rawMetrics = [
                'active_workload'   => $load,
                'category_tickets'  => $exp,
                'resolved_tickets'  => $totalResueltos,
                'sla_compliant'     => $dentroSla,
                'sla_rate'          => $slaRate,
                'mttr_hours'        => $rawMttr,
            ];

            $summary = "Score {$totalScore}/100 [Carga: {$breakdown['workload']} | Especialidad: {$breakdown['category']} | SLA: {$breakdown['sla']} | MTTR: {$breakdown['mttr']}]";

            $agent->assignment_score = $totalScore;
            $agent->assignment_breakdown = $breakdown;
            $agent->assignment_raw = $rawMetrics;
            $agent->assignment_rationale = [
                'total_score' => $totalScore,
                'weights'     => $weights,
                'breakdown'   => $breakdown,
                'raw_metrics' => $rawMetrics,
                'summary'     => $summary,
            ];

            return $agent;
        });

        // Ordenar candidatos por puntaje de idoneidad descendente
        return $scored->sortByDesc('assignment_score')->values();
    }

    /**
     * Calcula las métricas y puntuación de afinidad de un técnico respecto a un ticket específico.
     */
    public function getAgentMetricsForTicket(User $user, Ticket $ticket): array
    {
        $scored = $this->scoreCandidates(collect([$user]), $ticket);
        $agent = $scored->first();

        return [
            'score'       => $agent->assignment_score ?? 50.0,
            'breakdown'   => $agent->assignment_breakdown ?? [
                'workload' => 50, 'category' => 50, 'sla' => 50, 'mttr' => 50
            ],
            'raw_metrics' => $agent->assignment_raw ?? [
                'active_workload'  => 0,
                'category_tickets' => 0,
                'resolved_tickets' => 0,
                'sla_compliant'    => 0,
                'sla_rate'         => 100.0,
                'mttr_hours'       => 0.0,
            ],
            'summary'     => $agent->assignment_rationale['summary'] ?? '',
        ];
    }

    /**
     * Valida si un usuario cumple con todas las condiciones para atender el ticket.
     */
    public function validateEligibility(User $user, Ticket $ticket): array
    {
        $rolNombre = strtoupper($user->rol?->nombre ?? '');
        if (!in_array($rolNombre, ['TECNICO', 'DIRECTOR'])) {
            return [
                'eligible' => false,
                'reason'   => "El usuario '{$user->name}' no posee rol de soporte técnico ni director."
            ];
        }

        if (!$user->activo) {
            return [
                'eligible' => false,
                'reason'   => "El agente '{$user->name}' se encuentra actualmente inactivo."
            ];
        }

        // 1. Validar Categoría
        $ticketCatIds = $ticket->categorias->pluck('id')->toArray();
        if (empty($ticketCatIds) && $ticket->category_id) {
            $ticketCatIds = [$ticket->category_id];
        }

        $userCatIds = $user->categorias->pluck('id')->toArray();
        if (!empty($userCatIds) && !empty($ticketCatIds)) {
            $intersects = array_intersect($ticketCatIds, $userCatIds);
            if (empty($intersects)) {
                $catName = $ticket->category ? ($ticket->category->nombre ?? $ticket->category->name) : 'la categoría requerida';
                return [
                    'eligible' => false,
                    'reason'   => "El técnico '{$user->name}' no está designado para atender '{$catName}'."
                ];
            }
        }

        // 2. Validar Prioridad
        $userPriorityCodes = $user->prioridades->pluck('codigo')->map(fn($c) => strtolower($c))->toArray();
        $ticketPriorityCode = strtolower($ticket->priority);

        if (!empty($userPriorityCodes) && !in_array($ticketPriorityCode, $userPriorityCodes)) {
            return [
                'eligible' => false,
                'reason'   => "El técnico '{$user->name}' no está habilitado para tickets con prioridad '{$ticket->priority}'."
            ];
        }

        return ['eligible' => true, 'reason' => null];
    }
}
