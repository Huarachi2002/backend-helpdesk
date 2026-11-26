<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Exporta a Excel/CSV el reporte de métricas (Volumen, FRT y MTTR)
 * generado por App\Services\MetricsReportService.
 * Los tiempos se exportan en horas decimales para poder operar con ellos.
 */
class MetricsReportExport implements FromArray, ShouldAutoSize, WithTitle, WithStrictNullComparison, WithCustomCsvSettings
{
    private array $report;

    public function __construct(array $report)
    {
        $this->report = $report;
    }

    /** BOM UTF-8 para que Excel muestre bien las tildes al abrir el CSV. */
    public function getCsvSettings(): array
    {
        return ['use_bom' => true];
    }

    public function title(): string
    {
        return 'Métricas FRT y MTTR';
    }

    public function array(): array
    {
        $f = $this->report['filters'];
        $s = $this->report['summary'];

        $groupHeader = [
            'general'    => 'Período',
            'technician' => 'Técnico de Soporte',
            'category'   => 'Categoría',
        ][$f['level']] ?? 'Grupo';

        $rows = [
            ['Reporte de Métricas de Servicio — Volumen, FRT y MTTR'],
            ['Rango de fechas', $f['date_from'] . ' a ' . $f['date_to']],
            ['Nivel', $f['level_label']],
            ['Técnico', $f['technician']['name'] ?? 'Todos los técnicos'],
            ['Categoría', $f['category']['name'] ?? 'Todas las categorías'],
            ['Generado', $this->report['generated_at']],
            [],
            ['RESUMEN'],
            ['Total tickets', $s['total_tickets']],
            ['Tickets con primera respuesta', $s['tickets_respondidos']],
            ['Tickets sin respuesta', $s['tickets_sin_respuesta']],
            ['Tickets resueltos', $s['tickets_resueltos']],
            ['Tasa de resolución (%)', $s['tasa_resolucion']],
            ['FRT promedio (horas)', $s['frt_avg']],
            ['FRT mínimo (horas)', $s['frt_min']],
            ['FRT máximo (horas)', $s['frt_max']],
            ['MTTR promedio (horas)', $s['mttr_avg']],
            ['MTTR mínimo (horas)', $s['mttr_min']],
            ['MTTR máximo (horas)', $s['mttr_max']],
            [],
            ['DETALLE ' . mb_strtoupper($f['level_label'])],
            [
                $groupHeader,
                'Total tickets',
                'Con 1ª respuesta',
                'Resueltos',
                'Tasa resolución (%)',
                'FRT prom. (h)',
                'FRT mín. (h)',
                'FRT máx. (h)',
                'MTTR prom. (h)',
                'MTTR mín. (h)',
                'MTTR máx. (h)',
            ],
        ];

        foreach ($this->report['rows'] as $r) {
            $rows[] = [
                $r['label'],
                $r['total_tickets'],
                $r['tickets_respondidos'],
                $r['tickets_resueltos'],
                $r['tasa_resolucion'],
                $r['frt_avg'],
                $r['frt_min'],
                $r['frt_max'],
                $r['mttr_avg'],
                $r['mttr_min'],
                $r['mttr_max'],
            ];
        }

        return $rows;
    }
}
