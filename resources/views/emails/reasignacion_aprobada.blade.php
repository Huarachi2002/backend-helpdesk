@extends('emails.layout')

@section('content')
    <div class="greeting">¡Hola, {{ $recipientName }}!</div>
    <p class="message">
        Te informamos que la solicitud de reasignación para el ticket <strong>{{ $ticket->codigo }}</strong> ha sido <strong>APROBADA</strong> por la Dirección.
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
            <span class="ticket-label">Nuevo Técnico Asignado:</span>
            <span class="ticket-val" style="color: #2563eb;">{{ $nuevoTecnicoName }}</span>
        </div>
        <div class="ticket-row">
            <span class="ticket-label">Aprobado por:</span>
            <span class="ticket-val">{{ $revisorName }}</span>
        </div>
        <div class="ticket-row">
            <span class="ticket-label">Fecha de Resolución:</span>
            <span class="ticket-val">{{ now()->format('d/m/Y H:i') }}</span>
        </div>
    </div>

    @if(!empty($motivo))
        <div style="background-color: #f0fdf4; border-left: 4px solid #16a34a; padding: 12px 16px; border-radius: 4px; font-size: 13px; margin-bottom: 20px;">
            <strong style="color: #15803d;">Motivo de la Reasignación:</strong><br>
            {{ $motivo }}
        </div>
    @endif

    @if(!empty($notaResolucion))
        <div style="background-color: #f8fafc; border-left: 4px solid #64748b; padding: 12px 16px; border-radius: 4px; font-size: 13px; margin-bottom: 20px;">
            <strong>Nota de Dirección:</strong><br>
            {{ $notaResolucion }}
        </div>
    @endif

    <div class="btn-wrapper">
        <a href="{{ $actionUrl }}" class="btn" target="_blank">Ver Detalles del Ticket</a>
    </div>
@endsection
