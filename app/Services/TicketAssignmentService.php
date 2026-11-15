<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\User;

class TicketAssignmentService
{
    /**
     * Asigna automáticamente un ticket a un técnico disponible según:
     * 1. Rol de técnico o director y estado activo (activo = true).
     * 2. Categoría designada en usuario_categoria.
     * 3. Prioridad permitida en usuario_prioridad.
     * 4. Menor carga de tickets activos.
     */
    public function assign(Ticket $ticket): ?User
    {
        $eligibleAgents = $this->getEligibleAgents($ticket);

        // 1. Elegir técnico elegible con menor carga de tickets
        $chosen = $eligibleAgents->sortBy('assigned_tickets_count')->first();

        // 2. Si no hay técnicos específicamente restringidos para esa categoría/prioridad, fallback a cualquier técnico activo
        if (!$chosen) {
            $candidates = User::whereHas('rol', function ($q) {
                $q->whereIn('nombre', ['TECNICO', 'DIRECTOR']);
            })
            ->where('activo', true)
            ->withCount(['assignedTickets' => function ($query) {
                $query->whereNotIn('estado', ['RESUELTO', 'CERRADO']);
            }])
            ->get();

            $chosen = $candidates->sortBy('assigned_tickets_count')->first();
        }

        if ($chosen) {
            $ticket->tecnico_asignado_id = $chosen->id;
            $ticket->save();
            return $chosen;
        }

        $ticket->tecnico_asignado_id = null;
        $ticket->save();
        return null;
    }

    /**
     * Obtiene todos los técnicos elegibles para un ticket específico.
     */
    public function getEligibleAgents(Ticket $ticket)
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
