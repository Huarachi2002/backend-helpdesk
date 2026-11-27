<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Reporte de métricas de servicio para el Administrador (Director).
 *
 * Métricas calculadas sobre los tickets creados dentro del rango de fechas:
 *   - Volumen: cantidad de tickets (total, con primera respuesta y resueltos).
 *   - FRT  (First Response Time): fecha_respuesta - fecha_creacion, en horas.
 *   - MTTR (Mean Time To Resolve): fecha_resuelto - fecha_creacion, en horas.
 *
 * Niveles de agrupación:
 *   - general:    evolución por período (día o mes, según la amplitud del rango).
 *   - technician: por Técnico de Soporte asignado al ticket (tecnico_asignado_id).
 *   - category:   por Categoría del ticket (ticket_categoria).
 *
 * Los filtros de técnico y categoría se aplican en cualquier nivel.
 */
class MetricsReportService
{
    public const LEVELS = ['general', 'technician', 'category'];

    private const LEVEL_LABELS = [
        'general'    => 'General (por período)',
        'technician' => 'Por Técnico de Soporte',
        'category'   => 'Por Categoría del Ticket',
    ];

    /** Rangos de hasta esta cantidad de días se agrupan por día; los demás por mes. */
    private const MAX_DAYS_DAILY = 62;

    private const FRT_EXPR  = 'EXTRACT(EPOCH FROM (t.fecha_respuesta - t.fecha_creacion))/3600';
    private const MTTR_EXPR = 'EXTRACT(EPOCH FROM (t.fecha_resuelto - t.fecha_creacion))/3600';

    /**
     * @param array{date_from:string,date_to:string,level?:string,technician_id?:int|null,category_id?:int|null} $filters
     */
    public function build(array $filters): array
    {
        $from = Carbon::parse($filters['date_from'])->startOfDay();
        $to   = Carbon::parse($filters['date_to'])->endOfDay();
        $level = $filters['level'] ?? 'general';
        $technicianId = !empty($filters['technician_id']) ? (string) $filters['technician_id'] : null;
        $categoryId   = !empty($filters['category_id']) ? (int) $filters['category_id'] : null;

        $technician = $technicianId ? $this->technicians()->firstWhere('id', $technicianId) : null;
        $category   = $categoryId ? $this->categories()->firstWhere('id', $categoryId) : null;

        $summaryRow = $this->baseQuery($from, $to, $technicianId, $categoryId)
            ->selectRaw($this->aggregateSelect())
            ->first();

        $granularity = null;
        switch ($level) {
            case 'technician':
                $rows = $this->rowsByTechnician($from, $to, $technicianId, $categoryId);
                break;
            case 'category':
                $rows = $this->rowsByCategory($from, $to, $technicianId, $categoryId);
                break;
            default:
                $level = 'general';
                $granularity = $from->diffInDays($to) + 1 <= self::MAX_DAYS_DAILY ? 'day' : 'month';
                $rows = $this->rowsByPeriod($from, $to, $technicianId, $categoryId, $granularity);
        }

        return [
            'filters' => [
                'date_from'   => $from->toDateString(),
                'date_to'     => $to->toDateString(),
                'level'       => $level,
                'level_label' => self::LEVEL_LABELS[$level],
                'granularity' => $granularity,
                'technician'  => $technician ? ['id' => $technician->id, 'ci' => $technician->id, 'name' => $technician->name] : null,
                'category'    => $category ? ['id' => $category->id, 'name' => $category->name] : null,
            ],
            'summary'      => $this->formatMetrics($summaryRow),
            'rows'         => $rows,
            'generated_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Técnicos de soporte (rol TECNICO) para los filtros y el nivel por técnico.
     */
    public function technicians()
    {
        return DB::table('usuarios as u')
            ->join('roles as r', 'r.id', '=', 'u.rol_id')
            ->where('r.nombre', 'TECNICO')
            ->orderBy('u.nombre')
            ->orderBy('u.apellido')
            ->get(['u.ci', 'u.nombre', 'u.apellido', 'u.activo'])
            ->map(fn ($u) => (object) [
                'id'     => (string) $u->ci,
                'ci'     => (string) $u->ci,
                'name'   => trim($u->nombre . ' ' . ($u->apellido ?? '')),
                'active' => (bool) $u->activo,
            ]);
    }

    /**
     * Categorías de ticket para los filtros y el nivel por categoría.
     */
    public function categories()
    {
        return DB::table('categorias')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'activo'])
            ->map(fn ($c) => (object) [
                'id'     => (int) $c->id,
                'name'   => $c->nombre,
                'active' => (bool) $c->activo,
            ]);
    }

    // ------------------------------------------------------------------
    // Consultas
    // ------------------------------------------------------------------

    private function baseQuery(Carbon $from, Carbon $to, ?string $technicianId, ?int $categoryId): Builder
    {
        return DB::table('ticket as t')
            ->whereBetween('t.fecha_creacion', [$from, $to])
            ->when($technicianId, fn ($q) => $q->where('t.tecnico_asignado_ci', $technicianId))
            ->when($categoryId, fn ($q) => $q->whereExists(function ($sub) use ($categoryId) {
                $sub->select(DB::raw(1))
                    ->from('ticket_categoria as tc_f')
                    ->whereColumn('tc_f.ticket_codigo', 't.codigo')
                    ->where('tc_f.categoria_id', $categoryId);
            }));
    }

    private function aggregateSelect(): string
    {
        $frt  = self::FRT_EXPR;
        $mttr = self::MTTR_EXPR;

        return implode(', ', [
            'COUNT(*) as total_tickets',
            'COUNT(t.fecha_respuesta) as tickets_respondidos',
            'COUNT(t.fecha_resuelto) as tickets_resueltos',
            "AVG({$frt}) as frt_avg",
            "MIN({$frt}) as frt_min",
            "MAX({$frt}) as frt_max",
            "AVG({$mttr}) as mttr_avg",
            "MIN({$mttr}) as mttr_min",
            "MAX({$mttr}) as mttr_max",
        ]);
    }

    private function rowsByPeriod(Carbon $from, Carbon $to, ?string $technicianId, ?int $categoryId, string $granularity): array
    {
        $format = $granularity === 'day' ? 'YYYY-MM-DD' : 'YYYY-MM';

        $data = $this->baseQuery($from, $to, $technicianId, $categoryId)
            ->selectRaw("TO_CHAR(t.fecha_creacion, '{$format}') as periodo, " . $this->aggregateSelect())
            ->groupBy('periodo')
            ->get()
            ->keyBy('periodo');

        // Completar los períodos sin tickets para que la serie sea continua.
        $rows = [];
        if ($granularity === 'day') {
            foreach (CarbonPeriod::create($from->copy()->startOfDay(), '1 day', $to->copy()->startOfDay()) as $day) {
                $key = $day->format('Y-m-d');
                $rows[] = $this->makeRow($key, $day->format('d/m/Y'), $data->get($key));
            }
        } else {
            foreach (CarbonPeriod::create($from->copy()->startOfMonth(), '1 month', $to->copy()->startOfMonth()) as $month) {
                $key = $month->format('Y-m');
                $rows[] = $this->makeRow($key, ucfirst($month->locale('es')->isoFormat('MMMM YYYY')), $data->get($key));
            }
        }

        return $rows;
    }

    private function rowsByTechnician(Carbon $from, Carbon $to, ?string $technicianId, ?int $categoryId): array
    {
        $data = $this->baseQuery($from, $to, $technicianId, $categoryId)
            ->selectRaw('t.tecnico_asignado_ci as grupo, ' . $this->aggregateSelect())
            ->groupBy('t.tecnico_asignado_ci')
            ->get()
            ->keyBy(fn ($r) => $r->grupo === null ? 'none' : (string) $r->grupo);

        $technicians = $this->technicians();
        if ($technicianId) {
            $technicians = $technicians->where('id', $technicianId);
        } else {
            // Técnicos inactivos solo aparecen si tienen tickets en el rango.
            $technicians = $technicians->filter(fn ($t) => $t->active || $data->has($t->id));
        }

        $rows = [];
        foreach ($technicians as $tech) {
            $rows[] = $this->makeRow((string) $tech->id, $tech->name . ($tech->active ? '' : ' (inactivo)'), $data->get($tech->id));
        }

        // Tickets asignados a usuarios que ya no son técnicos y tickets sin asignar.
        $knownIds = $technicians->pluck('id')->all();
        foreach ($data as $key => $row) {
            if ($key === 'none' || in_array($key, $knownIds, true)) {
                continue;
            }
            $user = DB::table('usuarios')->where('ci', $key)->first(['nombre', 'apellido']);
            $label = $user ? trim($user->nombre . ' ' . ($user->apellido ?? '')) . ' (otro rol)' : "Usuario CI {$key}";
            $rows[] = $this->makeRow((string) $key, $label, $row);
        }
        if (!$technicianId && $data->has('none')) {
            $rows[] = $this->makeRow('none', 'Sin técnico asignado', $data->get('none'));
        }

        return $this->sortRows($rows);
    }

    private function rowsByCategory(Carbon $from, Carbon $to, ?string $technicianId, ?int $categoryId): array
    {
        // Un ticket con varias categorías cuenta en cada una de ellas.
        $data = $this->baseQuery($from, $to, $technicianId, $categoryId)
            ->leftJoin('ticket_categoria as tc', 'tc.ticket_codigo', '=', 't.codigo')
            ->when($categoryId, fn ($q) => $q->where('tc.categoria_id', $categoryId))
            ->selectRaw('tc.categoria_id as grupo, ' . $this->aggregateSelect())
            ->groupBy('tc.categoria_id')
            ->get()
            ->keyBy(fn ($r) => $r->grupo === null ? 'none' : (int) $r->grupo);

        $categories = $this->categories();
        if ($categoryId) {
            $categories = $categories->where('id', $categoryId);
        } else {
            $categories = $categories->filter(fn ($c) => $c->active || $data->has($c->id));
        }

        $rows = [];
        foreach ($categories as $cat) {
            $rows[] = $this->makeRow((string) $cat->id, $cat->name . ($cat->active ? '' : ' (inactiva)'), $data->get($cat->id));
        }
        if (!$categoryId && $data->has('none')) {
            $rows[] = $this->makeRow('none', 'Sin categoría', $data->get('none'));
        }

        return $this->sortRows($rows);
    }

    // ------------------------------------------------------------------
    // Formato
    // ------------------------------------------------------------------

    private function makeRow(string $key, string $label, $data): array
    {
        return array_merge(['key' => $key, 'label' => $label], $this->formatMetrics($data));
    }

    private function formatMetrics($row): array
    {
        $total       = (int) ($row->total_tickets ?? 0);
        $respondidos = (int) ($row->tickets_respondidos ?? 0);
        $resueltos   = (int) ($row->tickets_resueltos ?? 0);

        return [
            'total_tickets'       => $total,
            'tickets_respondidos' => $respondidos,
            'tickets_sin_respuesta' => $total - $respondidos,
            'tickets_resueltos'   => $resueltos,
            'tasa_resolucion'     => $total > 0 ? round($resueltos * 100 / $total, 1) : null,
            'frt_avg'  => $this->hours($row->frt_avg ?? null),
            'frt_min'  => $this->hours($row->frt_min ?? null),
            'frt_max'  => $this->hours($row->frt_max ?? null),
            'mttr_avg' => $this->hours($row->mttr_avg ?? null),
            'mttr_min' => $this->hours($row->mttr_min ?? null),
            'mttr_max' => $this->hours($row->mttr_max ?? null),
        ];
    }

    private function hours($value): ?float
    {
        return $value === null ? null : round((float) $value, 2);
    }

    private function sortRows(array $rows): array
    {
        usort($rows, function ($a, $b) {
            // Filas especiales ("Sin técnico", "Sin categoría") al final.
            if (($a['key'] === 'none') !== ($b['key'] === 'none')) {
                return $a['key'] === 'none' ? 1 : -1;
            }
            return [$b['total_tickets'], $a['label']] <=> [$a['total_tickets'], $b['label']];
        });

        return $rows;
    }

    /**
     * Convierte horas decimales a un texto legible ("2 h 15 min", "45 min", "3 d 4 h").
     */
    public static function humanHours(?float $hours): string
    {
        if ($hours === null) {
            return '—';
        }

        $minutes = (int) round($hours * 60);
        if ($minutes < 60) {
            return "{$minutes} min";
        }

        $days = intdiv($minutes, 1440);
        $h    = intdiv($minutes % 1440, 60);
        $m    = $minutes % 60;

        if ($days > 0) {
            return $h > 0 ? "{$days} d {$h} h" : "{$days} d";
        }

        return $m > 0 ? "{$h} h {$m} min" : "{$h} h";
    }
}
