<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Tickets — Helpdesk</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #333; }
        h1   { text-align: center; color: #2c3e50; font-size: 16px; margin-bottom: 4px; }
        p.subtitle { text-align: center; color: #7f8c8d; margin-top: 0; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th    { background: #2c3e50; color: #fff; padding: 6px 8px; text-align: left; font-size: 10px; }
        td    { padding: 5px 8px; border-bottom: 1px solid #ecf0f1; font-size: 9.5px; }
        tr:nth-child(even) { background: #f9f9f9; }
        .badge { padding: 2px 6px; border-radius: 3px; color: #fff; font-size: 8.5px; font-weight: bold; }
        .EN_ESPERA                     { background: #3498db; }
        .CLIENTE_ESPERANDO_RESPUESTA   { background: #e67e22; }
        .ESPERANDO_POR_CLIENTE         { background: #9b59b6; }
        .RESUELTO                      { background: #27ae60; }
        .CERRADO                       { background: #95a5a6; }
        .low                           { background: #27ae60; }
        .medium                        { background: #f39c12; }
        .high                          { background: #e74c3c; }
        .critical                      { background: #8e1a1a; }
        .footer { margin-top: 20px; text-align: right; font-size: 9px; color: #95a5a6; }
    </style>
</head>
<body>
    <h1>Reporte de Incidencias Técnicas — Helpdesk Campus</h1>
    <p class="subtitle">Generado el {{ now()->format('d/m/Y H:i') }} | Total: {{ count($tickets) }} tickets</p>

    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Asunto</th>
                <th>Categoría(s)</th>
                <th>Estado</th>
                <th>Prioridad</th>
                <th>Técnico Asignado</th>
                <th>Fecha Apertura</th>
                <th>Fecha Resolución</th>
            </tr>
        </thead>
        <tbody>
            @foreach($tickets as $ticket)
            <tr>
                <td><strong>{{ $ticket->codigo ?? '#' . $ticket->id }}</strong></td>
                <td>{{ $ticket->asunto ?? $ticket->title }}</td>
                <td>
                    @if($ticket->categorias && $ticket->categorias->count() > 0)
                        {{ $ticket->categorias->pluck('nombre')->join(', ') }}
                    @else
                        {{ $ticket->category->nombre ?? $ticket->category->name ?? 'General' }}
                    @endif
                </td>
                <td><span class="badge {{ $ticket->estado }}">{{ $ticket->estado }}</span></td>
                <td><span class="badge {{ strtolower($ticket->priority) }}">{{ strtoupper($ticket->priority) }}</span></td>
                <td>{{ $ticket->assignee->name ?? 'Sin asignar' }}</td>
                <td>{{ $ticket->fecha_creacion ? $ticket->fecha_creacion->format('d/m/Y H:i') : '—' }}</td>
                <td>{{ $ticket->fecha_resuelto ? $ticket->fecha_resuelto->format('d/m/Y H:i') : '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">Helpdesk AI Campus Virtual — Reporte generado automáticamente bajo nuevo modelo relacional</div>
</body>
</html>
