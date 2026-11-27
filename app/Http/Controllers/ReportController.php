<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\BotInteraction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Exports\TicketExport;
use App\Exports\MetricsReportExport;
use App\Services\MetricsReportService;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    public function volume(Request $request)
    {
        $cacheKey = 'reports_volume_' . md5(json_encode($request->all()));
        
        return Cache::remember($cacheKey, 3600, function () {
            $totalTickets = Ticket::count();

            // Agrupar por mes usando sintaxis de PostgreSQL (TO_CHAR)
            $byPeriod = Ticket::select(DB::raw("TO_CHAR(fecha_creacion, 'YYYY-MM') as month"), DB::raw('count(*) as count'))
                ->groupBy('month')
                ->orderBy('month')
                ->get();

            // Agrupar por categoría via ticket_categoria
            $byCategory = DB::table('ticket_categoria')
                ->join('categorias', 'ticket_categoria.categoria_id', '=', 'categorias.id')
                ->select('categorias.nombre as name', DB::raw('count(*) as count'))
                ->groupBy('categorias.nombre')
                ->get();

            return response()->json([
                'total_tickets' => $totalTickets,
                'by_period'     => $byPeriod,
                'by_category'   => $byCategory
            ]);
        });
    }

    public function impact(Request $request)
    {
        $cacheKey = 'reports_impact_' . md5(json_encode($request->all()));

        return Cache::remember($cacheKey, 3600, function () {
            $uniqueHumanUsers = Ticket::distinct('solicitante_ci')->count('solicitante_ci');
            $uniqueBotUsers = BotInteraction::distinct('usuario_ci')->count('usuario_ci');
            
            $totalBotInteractions = BotInteraction::count();
            $resolvedByBot = BotInteraction::where('resuelto', true)->count();
            
            $autonomousRate = $totalBotInteractions > 0 ? round(($resolvedByBot / $totalBotInteractions) * 100, 2) : 0;
            $escalationRate = $totalBotInteractions > 0 ? round((($totalBotInteractions - $resolvedByBot) / $totalBotInteractions) * 100, 2) : 0;

            return response()->json([
                'unique_users_human_support'            => $uniqueHumanUsers,
                'unique_users_bot_support'              => $uniqueBotUsers,
                'bot_autonomous_resolution_rate_percent' => $autonomousRate,
                'escalation_to_human_rate_percent'      => $escalationRate
            ]);
        });
    }

    public function times(Request $request)
    {
        $cacheKey = 'reports_times_' . md5(json_encode($request->all()));

        return Cache::remember($cacheKey, 3600, function () {
            // Lógica de cálculo MTTR y FRT adaptada para PostgreSQL (EXTRACT EPOCH)
            $times = Ticket::select(
                DB::raw('AVG(EXTRACT(EPOCH FROM (fecha_resuelto - fecha_creacion))/3600) as mttr_avg_hours'),
                DB::raw('MIN(EXTRACT(EPOCH FROM (fecha_resuelto - fecha_creacion))/3600) as mttr_min_hours'),
                DB::raw('MAX(EXTRACT(EPOCH FROM (fecha_resuelto - fecha_creacion))/3600) as mttr_max_hours'),
                DB::raw('AVG(EXTRACT(EPOCH FROM (fecha_respuesta - fecha_creacion))/3600) as frt_avg_hours')
            )
            ->whereNotNull('fecha_resuelto')
            ->first();

            return response()->json([
                'mttr_hours' => [
                    'average' => round($times->mttr_avg_hours ?? 0, 2),
                    'minimum' => round($times->mttr_min_hours ?? 0, 2),
                    'maximum' => round($times->mttr_max_hours ?? 0, 2),
                ],
                'frt_hours_average' => round($times->frt_avg_hours ?? 0, 2)
            ]);
        });
    }

    public function export(Request $request)
    {
        $format = $request->get('format', 'excel');

        if ($format === 'csv') {
            return Excel::download(new TicketExport, 'tickets_report.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        if ($format === 'pdf') {
            $tickets = Ticket::with(['categorias', 'assignee'])->get();
            $pdf = Pdf::loadView('exports.tickets_pdf', compact('tickets'));
            return $pdf->download('ticket_report.pdf');
        }

        return Excel::download(new TicketExport, 'tickets_report.xlsx');
    }

    public function dashboard(Request $request)
    {
        $cacheKey = 'reports_dashboard_' . md5(json_encode($request->all()));

        return Cache::remember($cacheKey, 1800, function () {
            // Tickets por estado
            $porEstado = Ticket::select('estado', DB::raw('count(*) as total'))
                ->groupBy('estado')
                ->get();

            // FRT y MTTR por mes (últimos 6 meses)
            $timesPerMonth = Ticket::select(
                DB::raw("TO_CHAR(fecha_creacion, 'YYYY-MM') as mes"),
                DB::raw('COUNT(*) as total_tickets'),
                DB::raw('ROUND(CAST(AVG(EXTRACT(EPOCH FROM (fecha_respuesta - fecha_creacion))/3600) AS numeric), 2) as frt_promedio_horas'),
                DB::raw('ROUND(CAST(AVG(EXTRACT(EPOCH FROM (fecha_resuelto - fecha_creacion))/3600) AS numeric), 2) as mttr_promedio_horas'),
                DB::raw('COUNT(fecha_resuelto) as tickets_resueltos')
            )
            ->where('fecha_creacion', '>=', now()->subMonths(6))
            ->groupBy('mes')
            ->orderBy('mes')
            ->get();

            // FRT y MTTR globales (totales)
            $tiemposGlobales = Ticket::select(
                DB::raw('COUNT(*) as n'),
                DB::raw('AVG(EXTRACT(EPOCH FROM (fecha_respuesta - fecha_creacion))/3600) as frt_promedio_horas'),
                DB::raw('AVG(EXTRACT(EPOCH FROM (fecha_resuelto - fecha_creacion))/3600) as mttr_promedio_horas'),
                DB::raw('COUNT(fecha_respuesta) as tickets_con_respuesta'),
                DB::raw('COUNT(fecha_resuelto) as tickets_resueltos')
            )
            ->first();

            return response()->json([
                'por_estado'        => $porEstado,
                'tiempos_por_mes'   => $timesPerMonth,
                'tiempos_globales'  => $tiemposGlobales,
            ]);
        });
    }

    // ------------------------------------------------------------------
    // Reporte de Métricas FRT / MTTR (Administrador y Técnico de Soporte)
    // ------------------------------------------------------------------

    /**
     * Opciones para los filtros del reporte: técnicos de soporte y categorías.
     */
    public function metricsOptions(MetricsReportService $service)
    {
        return response()->json([
            'technicians' => $service->technicians()->values(),
            'categories'  => $service->categories()->values(),
            'levels'      => MetricsReportService::LEVELS,
        ]);
    }

    /**
     * Volumen, FRT y MTTR en un rango de fechas, por nivel (general, técnico o categoría),
     * filtrable por técnico y por categoría.
     */
    public function metrics(Request $request, MetricsReportService $service)
    {
        return response()->json($service->build($this->validateMetricsFilters($request)));
    }

    public function metricsExport(Request $request, MetricsReportService $service)
    {
        $filters = $this->validateMetricsFilters($request);
        $format  = $request->validate(['format' => 'nullable|in:pdf,excel,csv'])['format'] ?? 'excel';
        $report  = $service->build($filters);

        $name = sprintf(
            'reporte_metricas_%s_%s_%s',
            $report['filters']['level'],
            $report['filters']['date_from'],
            $report['filters']['date_to']
        );

        if ($format === 'pdf') {
            return Pdf::loadView('exports.metrics_pdf', ['report' => $report])
                ->setPaper('a4', 'landscape')
                ->download("{$name}.pdf");
        }

        if ($format === 'csv') {
            return Excel::download(new MetricsReportExport($report), "{$name}.csv", \Maatwebsite\Excel\Excel::CSV);
        }

        return Excel::download(new MetricsReportExport($report), "{$name}.xlsx");
    }

    private function validateMetricsFilters(Request $request): array
    {
        $data = $request->validate([
            'date_from'     => 'nullable|date_format:Y-m-d',
            'date_to'       => 'nullable|date_format:Y-m-d',
            'level'         => 'nullable|in:' . implode(',', MetricsReportService::LEVELS),
            'technician_id' => 'nullable|string|exists:usuarios,ci',
            'category_id'   => 'nullable|integer|exists:categorias,id',
        ], [
            'date_from.date_format' => 'La fecha "Desde" debe tener el formato AAAA-MM-DD.',
            'date_to.date_format'   => 'La fecha "Hasta" debe tener el formato AAAA-MM-DD.',
            'technician_id.exists'  => 'El técnico seleccionado no existe.',
            'category_id.exists'    => 'La categoría seleccionada no existe.',
        ]);

        // Por defecto: últimos 30 días.
        $data['date_to']   = $data['date_to'] ?? now()->toDateString();
        $data['date_from'] = $data['date_from'] ?? now()->subDays(29)->toDateString();

        if ($data['date_from'] > $data['date_to']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'date_to' => 'La fecha "Hasta" no puede ser anterior a la fecha "Desde".',
            ]);
        }

        return $data;
    }
}
