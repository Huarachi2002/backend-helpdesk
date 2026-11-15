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
        return Ticket::query()->with(['categorias', 'assignee', 'creator']);
    }

    public function headings(): array
    {
        return [
            'ID',
            'Código',
            'Asunto',
            'Estado',
            'Prioridad',
            'Categoría(s)',
            'Solicitante',
            'Técnico Asignado',
            'Fecha Apertura',
            'Fecha 1ª Respuesta',
            'Fecha Resolución'
        ];
    }

    public function map($ticket): array
    {
        $cats = $ticket->categorias && $ticket->categorias->count() > 0
            ? $ticket->categorias->pluck('nombre')->join(', ')
            : ($ticket->category->nombre ?? $ticket->category->name ?? 'General');

        return [
            $ticket->id,
            $ticket->codigo,
            $ticket->asunto ?? $ticket->title,
            $ticket->estado,
            strtoupper($ticket->priority),
            $cats,
            $ticket->creator?->name ?? 'Solicitante Moodle',
            $ticket->assignee?->name ?? 'Sin Asignar',
            $ticket->fecha_creacion ? $ticket->fecha_creacion->format('Y-m-d H:i:s') : '—',
            $ticket->fecha_respuesta ? $ticket->fecha_respuesta->format('Y-m-d H:i:s') : '—',
            $ticket->fecha_resuelto ? $ticket->fecha_resuelto->format('Y-m-d H:i:s') : '—',
        ];
    }
}
