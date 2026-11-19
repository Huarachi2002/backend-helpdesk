@extends('emails.layout')

@section('content')
    <div class="greeting">Estimado(a) {{ $recipientName }},</div>
    <p class="message">
        Te informamos que tu solicitud de reasignación para el ticket <strong>{{ $ticket->codigo }}</strong> ha sido <strong>RECHAZADA</strong> por la Dirección.
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
            <span class="ticket-label">Técnico que solicitó:</span>
            <span class="ticket-val">{{ $recipientName }}</span>
        </div>
        <div class="ticket-row">
            <span class="ticket-label">Revisado por:</span>
            <span class="ticket-val">{{ $revisorName }}</span>
        </div>
        <div class="ticket-row">
            <span class="ticket-label">Fecha de Respuesta:</span>
            <span class="ticket-val">{{ now()->format('d/m/Y H:i') }}</span>
        </div>
    </div>

    @if(!empty($motivoRechazo))
        <div style="background-color: #fef2f2; border-left: 4px solid #ef4444; padding: 12px 16px; border-radius: 4px; font-size: 13px; margin-bottom: 20px;">
            <strong style="color: #b91c1c;">Motivo del Rechazo:</strong><br>
            {{ $motivoRechazo }}
        </div>
    @endif

    <p class="message" style="font-size: 13px; color: #475569;">
        El ticket continuará bajo tu responsabilidad según la asignación vigente.
    </p>

    <div class="btn-wrapper">
        <a href="{{ $actionUrl }}" class="btn" target="_blank">Acceder al Ticket</a>
    </div>
@endsection
