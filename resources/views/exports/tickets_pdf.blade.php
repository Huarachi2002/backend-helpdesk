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
        td    { padding: 5px 8px; border-bottom: 1px solid #ecf0f1; }
        tr:nth-child(even) { background: #f9f9f9; }
        .badge { padding: 2px 6px; border-radius: 3px; color: #fff; font-size: 9px; font-weight: bold; }
        .open        { background: #3498db; }
        .in_progress { background: #e67e22; }
        .waiting     { background: #9b59b6; }
        .resolved    { background: #27ae60; }
        .closed      { background: #95a5a6; }
        .low         { background: #27ae60; }
        .medium      { background: #f39c12; }
        .high        { background: #e74c3c; }
        .critical    { background: #8e1a1a; }
        .footer { margin-top: 20px; text-align: right; font-size: 9px; color: #95a5a6; }
    </style>
</head>
<body>
    <h1>Reporte de Tickets — Helpdesk</h1>
    <p class="subtitle">Generado el {{ now()->format('d/m/Y H:i') }} | Total: {{ count($tickets) }} tickets</p>

    <table>
        <thead>
            <tr>
                <th>#ID</th>
                <th>Título</th>
                <th>Categoría</th>
                <th>Estado</th>
                <th>Prioridad</th>
                <th>Asignado a</th>
                <th>Creado</th>
                <th>Resuelto</th>
            </tr>
        </thead>
        <tbody>
            @foreach($tickets as $ticket)
            <tr>
                <td>{{ $ticket->id }}</td>
                <td>{{ $ticket->title }}</td>
                <td>{{ $ticket->category->name ?? '—' }}</td>
                <td><span class="badge {{ $ticket->status }}">{{ strtoupper($ticket->status) }}</span></td>
                <td><span class="badge {{ $ticket->priority }}">{{ strtoupper($ticket->priority) }}</span></td>
                <td>{{ $ticket->assignee->name ?? 'Sin asignar' }}</td>
                <td>{{ $ticket->created_at->format('d/m/Y') }}</td>
                <td>{{ $ticket->resolved_at ? $ticket->resolved_at->format('d/m/Y') : '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">Moodle Helpdesk &amp; Admin Panel — Reporte generado automáticamente</div>
</body>
</html>
