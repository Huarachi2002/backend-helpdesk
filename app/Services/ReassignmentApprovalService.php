<?php

namespace App\Services;

use App\Models\Asignacion;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Aprobación jerárquica de las solicitudes de REASIGNACIÓN (Decisión D5).
 *
 * Una solicitud de reasignación de un técnico la resuelve su cadena superior en la
 * Estructura Organizacional: su superior directo (p. ej. el Coordinador de su sección)
 * y los cargos por encima de él hasta la Jefatura del DEDTE.
 *
 * Respaldo: si el solicitante no tiene cargo o su cadena superior no tiene personal
 * activo, la solicitud la puede resolver cualquier Administrador (comportamiento anterior).
 */
class ReassignmentApprovalService
{
    /**
     * Técnico que envió la solicitud (quien tenía el ticket al momento de pedirla).
     */
    public function requester(Asignacion $solicitud, Ticket $ticket): ?User
    {
        $solicitud->loadMissing('participantes.usuario.cargo');
        $solicitante = $solicitud->solicitante();
        if ($solicitante) {
            $solicitante->loadMissing('cargo');
            return $solicitante;
        }

        $ticket->loadMissing('assignee.cargo');
        return $ticket->assignee;
    }

    /**
     * Usuarios que pueden aprobar o rechazar la solicitud.
     */
    public function approvers(Asignacion $solicitud, Ticket $ticket): Collection
    {
        $requester = $this->requester($solicitud, $ticket);
        $cargo = $requester?->cargo;

        if ($cargo) {
            $superiores = $cargo->codigosSuperiores();
            if (!empty($superiores)) {
                $jefes = User::with('cargo')
                    ->whereIn('cargo_codigo', $superiores)
                    ->where('activo', true)
                    ->where('ci', '!=', $requester->ci)
                    ->get()
                    // Primero el superior directo, luego hacia la Jefatura
                    ->sortBy(fn (User $u) => array_search($u->cargo_codigo, $superiores, true))
                    ->values();

                if ($jefes->isNotEmpty()) {
                    return $jefes;
                }
            }
        }

        return $this->allAdmins();
    }

    /**
     * Indica si $user puede resolver (aprobar/rechazar) la solicitud.
     */
    public function canResolve(User $user, Asignacion $solicitud, Ticket $ticket): bool
    {
        return $this->approvers($solicitud, $ticket)->contains('ci', $user->ci);
    }

    /**
     * Resumen legible de los aprobadores (para mensajes y la interfaz).
     */
    public function approversSummary(Collection $approvers): array
    {
        return $approvers->map(fn (User $u) => [
            'id'    => $u->ci,
            'ci'    => $u->ci,
            'name'  => $u->name,
            'cargo' => $u->cargo?->nombre,
            'nivel' => $u->cargo?->nivel,
        ])->values()->all();
    }

    private function allAdmins(): Collection
    {
        return User::with('cargo')
            ->whereHas('rol', fn ($q) => $q->whereIn('nombre', ['DIRECTOR', 'ADMINISTRADOR', 'ADMIN']))
            ->where('activo', true)
            ->get();
    }
}
