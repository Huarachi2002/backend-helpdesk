<?php

namespace App\Http\Controllers;

use App\Models\PageVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PageVisitController extends Controller
{
    /**
     * Normaliza la ruta recibida desde el frontend (sin query string, sin slash final redundante).
     */
    private function normalizePath(string $rawPath): string
    {
        $path = parse_url(trim($rawPath), PHP_URL_PATH) ?: '/';
        $path = '/' . ltrim($path, '/');
        if (strlen($path) > 1) {
            $path = rtrim($path, '/');
        }
        return substr($path, 0, 255);
    }

    /**
     * Registra una visita a una página/vista específica y devuelve el contador global actualizado.
     * Endpoint público para que funcione en TODAS las vistas (incluyendo /login y todos los roles).
     * Si el visitante envía un token Sanctum válido, registra su `ci` en `ultimo_usuario_ci` (relación 1:N).
     */
    public function track(Request $request)
    {
        $validated = $request->validate([
            'ruta' => ['required', 'string', 'max:255'],
            'titulo' => ['nullable', 'string', 'max:120'],
        ]);

        $ruta = $this->normalizePath($validated['ruta']);
        $titulo = isset($validated['titulo']) ? trim($validated['titulo']) : null;
        $usuarioCi = optional(auth('sanctum')->user())->ci;
        $now = now();

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement(
                'INSERT INTO visitas_pagina (ruta, titulo, visitas, ultimo_usuario_ci, ultima_visita, fecha_creacion, fecha_actualizacion)
                 VALUES (?, ?, 1, ?, ?, ?, ?)
                 ON CONFLICT (ruta) DO UPDATE SET
                   visitas = visitas_pagina.visitas + 1,
                   titulo = COALESCE(EXCLUDED.titulo, visitas_pagina.titulo),
                   ultimo_usuario_ci = COALESCE(EXCLUDED.ultimo_usuario_ci, visitas_pagina.ultimo_usuario_ci),
                   ultima_visita = EXCLUDED.ultima_visita,
                   fecha_actualizacion = EXCLUDED.fecha_actualizacion',
                [$ruta, $titulo, $usuarioCi, $now, $now, $now]
            );
        } else {
            $record = PageVisit::firstOrCreate(
                ['ruta' => $ruta],
                [
                    'titulo' => $titulo,
                    'visitas' => 0,
                    'ultimo_usuario_ci' => $usuarioCi,
                    'ultima_visita' => $now,
                ]
            );
            $record->increment('visitas');
            if ($titulo && $record->titulo !== $titulo) {
                $record->titulo = $titulo;
            }
            if ($usuarioCi) {
                $record->ultimo_usuario_ci = $usuarioCi;
            }
            $record->ultima_visita = $now;
            $record->save();
        }

        $visit = PageVisit::find($ruta);
        $totalSitio = (int) PageVisit::sum('visitas');

        return response()->json([
            'ruta' => $visit->ruta,
            'titulo' => $visit->titulo,
            'visitas' => (int) $visit->visitas,
            'ultimo_usuario_ci' => $visit->ultimo_usuario_ci,
            'total_sitio' => $totalSitio,
            'ultima_visita' => optional($visit->ultima_visita)->toIso8601String(),
        ]);
    }

    /**
     * Consulta el contador actual de una ruta específica o el resumen global sin incrementar.
     */
    public function index(Request $request)
    {
        if ($request->filled('ruta')) {
            $ruta = $this->normalizePath((string) $request->query('ruta'));
            $visit = PageVisit::find($ruta);
            $totalSitio = (int) PageVisit::sum('visitas');

            return response()->json([
                'ruta' => $ruta,
                'titulo' => $visit ? $visit->titulo : null,
                'visitas' => $visit ? (int) $visit->visitas : 0,
                'ultimo_usuario_ci' => $visit ? $visit->ultimo_usuario_ci : null,
                'total_sitio' => $totalSitio,
                'ultima_visita' => $visit && $visit->ultima_visita ? $visit->ultima_visita->toIso8601String() : null,
            ]);
        }

        $pages = PageVisit::with('ultimoUsuario:ci,nombre,apellido,correo')
            ->orderByDesc('visitas')
            ->get();

        return response()->json([
            'total_sitio' => (int) $pages->sum('visitas'),
            'paginas' => $pages,
        ]);
    }
}
