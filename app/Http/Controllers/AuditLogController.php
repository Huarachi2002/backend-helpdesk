<?php
namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::with('usuario:id,nombre,apellido,correo')
            ->orderBy('created_at', 'desc');

        if ($request->filled('tipo_evento')) {
            $query->where('tipo_evento', $request->tipo_evento);
        }
        if ($request->filled('usuario_id')) {
            $query->where('usuario_id', $request->usuario_id);
        }
        if ($request->filled('fecha_desde')) {
            $query->whereDate('created_at', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('created_at', '<=', $request->fecha_hasta);
        }
        if ($request->filled('ip')) {
            $query->where('ip', 'like', '%' . $request->ip . '%');
        }

        return response()->json($query->paginate(50));
    }

    public function stats()
    {
        // Recursos con mayor índice de acceso
        $topRecursos = AuditLog::where('tipo_evento', 'ACCESS')
            ->select('recurso', DB::raw('count(*) as total'))
            ->groupBy('recurso')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // Conteo por tipo de evento
        $porEvento = AuditLog::select('tipo_evento', DB::raw('count(*) as total'))
            ->groupBy('tipo_evento')
            ->get();

        // Intentos de login fallidos en las últimas 24h
        $loginsFallidos24h = AuditLog::where('tipo_evento', 'LOGIN_FAIL')
            ->where('created_at', '>=', now()->subDay())
            ->count();

        // Logins exitosos últimas 24h
        $loginsExitosos24h = AuditLog::where('tipo_evento', 'LOGIN_OK')
            ->where('created_at', '>=', now()->subDay())
            ->count();

        // Actividad por día (últimos 7 días)
        $actividadReciente = AuditLog::select(
                DB::raw("TO_CHAR(created_at, 'YYYY-MM-DD') as fecha"),
                DB::raw('count(*) as total')
            )
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->get();

        return response()->json([
            'top_recursos'          => $topRecursos,
            'por_evento'            => $porEvento,
            'logins_fallidos_24h'   => $loginsFallidos24h,
            'logins_exitosos_24h'   => $loginsExitosos24h,
            'actividad_reciente'    => $actividadReciente,
        ]);
    }
}
