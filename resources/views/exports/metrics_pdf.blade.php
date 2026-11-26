@php
    use App\Services\MetricsReportService as M;
    $f = $report['filters'];
    $s = $report['summary'];
    $groupHeader = ['general' => 'Período', 'technician' => 'Técnico de Soporte', 'category' => 'Categoría'][$f['level']] ?? 'Grupo';
    $fmtDate = fn ($d) => \Carbon\Carbon::parse($d)->format('d/m/Y');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Métricas FRT y MTTR — Helpdesk</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #333; }
        h1   { text-align: center; color: #2c3e50; font-size: 16px; margin-bottom: 4px; }
        p.subtitle { text-align: center; color: #7f8c8d; margin-top: 0; font-size: 10px; }
        h2   { font-size: 12px; color: #2c3e50; margin: 18px 0 6px; border-bottom: 2px solid #2c3e50; padding-bottom: 3px; }
        table { width: 100%; border-collapse: collapse; }
        .filters td { padding: 3px 6px; font-size: 10px; border: none; }
        .filters td.k { color: #7f8c8d; width: 110px; }
        .kpis td { width: 25%; padding: 8px; border: 1px solid #ecf0f1; vertical-align: top; }
        .kpis .label { font-size: 9px; color: #7f8c8d; text-transform: uppercase; }
        .kpis .value { font-size: 15px; font-weight: bold; color: #2c3e50; margin-top: 2px; }
        .kpis .note  { font-size: 8.5px; color: #95a5a6; margin-top: 2px; }
        .detail th { background: #2c3e50; color: #fff; padding: 5px 6px; text-align: left; font-size: 9px; }
        .detail th.num, .detail td.num { text-align: right; }
        .detail td { padding: 4px 6px; border-bottom: 1px solid #ecf0f1; font-size: 9px; }
        .detail tr:nth-child(even) td { background: #f9f9f9; }
        .muted { color: #95a5a6; }
        .footer { margin-top: 20px; font-size: 8.5px; color: #95a5a6; }
    </style>
</head>
<body>
    <h1>Reporte de Métricas de Servicio — FRT y MTTR</h1>
    <p class="subtitle">Generado el {{ \Carbon\Carbon::parse($report['generated_at'])->format('d/m/Y H:i') }}</p>

    <table class="filters">
        <tr><td class="k">Rango de fechas</td><td>{{ $fmtDate($f['date_from']) }} al {{ $fmtDate($f['date_to']) }}</td></tr>
        <tr><td class="k">Nivel</td><td>{{ $f['level_label'] }}</td></tr>
        <tr><td class="k">Técnico</td><td>{{ $f['technician']['name'] ?? 'Todos los técnicos' }}</td></tr>
        <tr><td class="k">Categoría</td><td>{{ $f['category']['name'] ?? 'Todas las categorías' }}</td></tr>
    </table>

    <h2>Resumen</h2>
    <table class="kpis">
        <tr>
            <td>
                <div class="label">Total tickets</div>
                <div class="value">{{ $s['total_tickets'] }}</div>
                <div class="note">{{ $s['tickets_resueltos'] }} resueltos ({{ $s['tasa_resolucion'] ?? 0 }}%)</div>
            </td>
            <td>
                <div class="label">FRT promedio</div>
                <div class="value">{{ M::humanHours($s['frt_avg']) }}</div>
                <div class="note">{{ $s['tickets_respondidos'] }} con primera respuesta</div>
            </td>
            <td>
                <div class="label">MTTR promedio</div>
                <div class="value">{{ M::humanHours($s['mttr_avg']) }}</div>
                <div class="note">sobre {{ $s['tickets_resueltos'] }} resueltos</div>
            </td>
            <td>
                <div class="label">Sin respuesta</div>
                <div class="value">{{ $s['tickets_sin_respuesta'] }}</div>
                <div class="note">tickets aún sin atender</div>
            </td>
        </tr>
        <tr>
            <td colspan="2"><div class="label">FRT mínimo / máximo</div><div class="note" style="font-size:10px;color:#333">{{ M::humanHours($s['frt_min']) }} / {{ M::humanHours($s['frt_max']) }}</div></td>
            <td colspan="2"><div class="label">MTTR mínimo / máximo</div><div class="note" style="font-size:10px;color:#333">{{ M::humanHours($s['mttr_min']) }} / {{ M::humanHours($s['mttr_max']) }}</div></td>
        </tr>
    </table>

    <h2>Detalle — {{ $f['level_label'] }}</h2>
    <table class="detail">
        <thead>
            <tr>
                <th>{{ $groupHeader }}</th>
                <th class="num">Tickets</th>
                <th class="num">Con 1ª resp.</th>
                <th class="num">Resueltos</th>
                <th class="num">FRT prom.</th>
                <th class="num">FRT mín. / máx.</th>
                <th class="num">MTTR prom.</th>
                <th class="num">MTTR mín. / máx.</th>
            </tr>
        </thead>
        <tbody>
            @forelse($report['rows'] as $r)
            <tr>
                <td><strong>{{ $r['label'] }}</strong></td>
                <td class="num">{{ $r['total_tickets'] }}</td>
                <td class="num">{{ $r['tickets_respondidos'] }}</td>
                <td class="num">{{ $r['tickets_resueltos'] }}</td>
                <td class="num">{{ M::humanHours($r['frt_avg']) }}</td>
                <td class="num muted">{{ M::humanHours($r['frt_min']) }} / {{ M::humanHours($r['frt_max']) }}</td>
                <td class="num">{{ M::humanHours($r['mttr_avg']) }}</td>
                <td class="num muted">{{ M::humanHours($r['mttr_min']) }} / {{ M::humanHours($r['mttr_max']) }}</td>
            </tr>
            @empty
            <tr><td colspan="8" class="muted">No hay datos para los filtros seleccionados.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        FRT (First Response Time): tiempo entre la creación del ticket y la primera respuesta del soporte.
        MTTR (Mean Time To Resolve): tiempo entre la creación del ticket y su resolución.
        Se consideran los tickets creados dentro del rango de fechas; la atribución por técnico usa el técnico asignado actualmente.
    </div>
</body>
</html>
