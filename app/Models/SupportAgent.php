<?php

namespace App\Models;

/**
 * Clase de soporte / compatibilidad transitoria.
 * En la nueva estructura, los agentes y sus configuraciones
 * se gestionan directamente a través de usuarios, roles,
 * usuario_categoria y usuario_prioridad.
 */
class SupportAgent
{
    protected User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function __get($name)
    {
        if ($name === 'tier_level') {
            return 2;
        }
        if ($name === 'max_active_tickets') {
            return 10;
        }
        if ($name === 'is_active') {
            return (bool) $this->user->activo;
        }
        if ($name === 'allowed_priorities') {
            return $this->user->prioridades->pluck('codigo')->map(fn($c) => strtolower($c))->toArray();
        }
        if ($name === 'categories') {
            return $this->user->categorias;
        }
        return null;
    }
}
