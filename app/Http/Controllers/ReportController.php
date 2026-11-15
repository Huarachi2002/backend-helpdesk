<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\BotInteraction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Exports\TicketExport;
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
            $uniqueHumanUsers = Ticket::distinct('solicitante_id')->count('solicitante_id');
            $uniqueBotUsers = BotInteraction::distinct('user_id')->count('user_id');
            
            $totalBotInteractions = BotInteraction::count();
            $resolvedByBot = BotInteraction::where('resolved', true)->count();
            
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
}
