<?php

namespace App\Exports;

use App\Models\Ticket;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TicketExport implements FromQuery, WithHeadings, WithMapping
{
    public function query()
    {
        return Ticket::query()->with(['category', 'assignee']);
    }

    public function headings(): array
    {
        return ['ID', 'Título', 'Estado', 'Prioridad', 'Categoría', 'Agente', 'Fecha Creación', 'Fecha Resolución'];
    }

    public function map($ticket): array
    {
        return [
            $ticket->id,
            $ticket->title,
            $ticket->status,
            $ticket->priority,
            $ticket->category->name ?? 'N/A',
            $ticket->assignee->email ?? 'Sin Asignar',
            $ticket->created_at->format('Y-m-d H:i:s'),
            $ticket->resolved_at ? $ticket->resolved_at->format('Y-m-d H:i:s') : 'N/A',
        ];
    }
}