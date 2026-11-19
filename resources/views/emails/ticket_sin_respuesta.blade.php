@extends('emails.layout')

@section('content')
    <div class="greeting">¡Hola, {{ $recipientName }}!</div>
    <p class="message">
        Te escribimos para informarte sobre el estado de tu ticket <strong>{{ $ticket->codigo }}</strong>. Sabemos que es importante para ti y queremos asegurarte que nuestro equipo técnico sigue trabajando en él.
    </p>

    <div class="ticket-card">
        <div class="ticket-row">
            <span class="ticket-label">Código:</span>
            <span class="ticket-val">{{ $ticket->codigo }}</span>
        </div>
        <div class="ticket-row">
            <span class="ticket-label">Asunto:</span>
            <span class="ticket-val">{{ $ticket->asunto }}</span>
        </div>
        <div class="ticket-row">
            <span class="ticket-label">Categoría:</span>
            <span class="ticket-val">{{ $ticket->category?->nombre ?? 'General' }}</span>
        </div>
        <div class="ticket-row">
            <span class="ticket-label">Días en Espera:</span>
            <span class="ticket-val" style="color: #b45309; font-weight: 700;">{{ $diasSinRespuesta }} días</span>
        </div>
        <div class="ticket-row">
            <span class="ticket-label">Técnico Asignado:</span>
            <span class="ticket-val">{{ $ticket->assignee?->name ?? 'Equipo Central de Soporte' }}</span>
        </div>
    </div>

    <p class="message" style="font-size: 13px; color: #475569;">
        Hemos elevado una alerta interna de prioridad al equipo para agilizar la resolución de tu solicitud a la brevedad posible.
    </p>

    <div class="btn-wrapper">
        <a href="{{ $actionUrl }}" class="btn" target="_blank">Consultar Estado de mi Ticket</a>
    </div>
@endsection
