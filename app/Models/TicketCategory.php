<?php

namespace App\Models;

/**
 * Alias de Categoria para compatibilidad con código existente.
 */
class TicketCategory extends Categoria
{
    protected $table = 'categorias';
}
