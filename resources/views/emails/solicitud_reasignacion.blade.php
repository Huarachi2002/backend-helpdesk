@extends('emails.layout')

@section('content')
    <div class="greeting">Estimada Dirección / Administración,</div>
    <p class="message">
        El técnico de soporte <strong>{{ $solicitanteName }}</strong> ha solicitado la <strong>reasignación</strong> del siguiente ticket:
    </p>

    <div class="ticket-card">
        <div class="ticket-row">
            <span class="ticket-label">Código Ticket:</span>
            <span class="ticket-val">{{ $ticket->codigo }}</span>
        </div>
        <div class="ticket-row">
            <span class="ticket-label">Asunto:</span>
            <span class="ticket-val">{{ $ticket->asunto }}</span>
        </div>
        <div class="ticket-row">
            <span class="ticket-label">Técnico Actual:</span>
            <span class="ticket-val">{{ $tecnicoAnteriorName }}</span>
        </div>
        <div class="ticket-row">
            <span class="ticket-label">Técnico Propuesto:</span>
            <span class="ticket-val">{{ $tecnicoPropuestoName }}</span>
        </div>
        <div class="ticket-row">
            <span class="ticket-label">Fecha Solicitud:</span>
            <span class="ticket-val">{{ $fechaSolicitud }}</span>
        </div>
    </div>

    <div style="background-color: #fffbeb; border-left: 4px solid #f59e0b; padding: 12px 16px; border-radius: 4px; font-size: 13px; margin-bottom: 20px;">
        <strong style="color: #b45309;">Motivo o Justificación Técnica:</strong><br>
        {{ $motivo }}
    </div>

    <p class="message" style="font-size: 13px; color: #64748b;">
        Por favor ingresa a la plataforma web para autorizar o rechazar esta solicitud de reasignación.
    </p>

    <div class="btn-wrapper">
        <a href="{{ $actionUrl }}" class="btn" target="_blank">Revisar Solicitud en la Plataforma</a>
    </div>
@endsection
