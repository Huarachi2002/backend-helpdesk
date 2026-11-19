@extends('emails.layout')

@section('content')
    <div class="greeting">¡Hola, {{ $recipientName }}!</div>
    <p class="message">
        A continuación te presentamos el <strong>Resumen Diario de Tickets Pendientes</strong> bajo tu responsabilidad o supervisión:
    </p>

    <div style="background-color: #eff6ff; border-radius: 8px; padding: 14px 20px; margin-bottom: 24px; border: 1px solid #bfdbfe;">
        <span style="font-size: 24px; font-weight: 700; color: #1e40af;">{{ count($tickets) }}</span>
        <span style="font-size: 14px; font-weight: 600; color: #1e3a8a; margin-left: 8px;">tickets pendientes requieren atención prioritaria</span>
    </div>

    <table style="width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 24px;">
        <thead>
            <tr style="background-color: #f8fafc; border-bottom: 2px solid #e2e8f0; text-align: left;">
                <th style="padding: 10px 8px; color: #64748b;">Código</th>
                <th style="padding: 10px 8px; color: #64748b;">Asunto</th>
                <th style="padding: 10px 8px; color: #64748b;">Prioridad</th>
                <th style="padding: 10px 8px; color: #64748b;">Inactividad</th>
            </tr>
        </thead>
        <tbody>
            @foreach($tickets as $t)
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 10px 8px; font-weight: 700; color: #2563eb;">
                        {{ $t->codigo }}
                    </td>
                    <td style="padding: 10px 8px; color: #1e293b; max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        {{ $t->asunto }}
                    </td>
                    <td style="padding: 10px 8px;">
                        <span class="badge badge-{{ strtolower($t->priority) }}">{{ strtoupper($t->priority) }}</span>
                    </td>
                    <td style="padding: 10px 8px; color: #64748b; font-size: 12px;">
                        {{ round(now()->diffInHours($t->fecha_actualizacion ?? $t->fecha_creacion)) }}h
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="btn-wrapper">
        <a href="{{ $actionUrl }}" class="btn" target="_blank">Ir a la Bandeja de Tickets</a>
    </div>
@endsection
