@extends('emails.layout')

@section('content')
    <div class="greeting">¡Hola, {{ $recipientName }}!</div>
    <p class="message">
        Este es un recordatorio automático sobre el ticket <strong>{{ $ticket->codigo }}</strong>, el cual se encuentra pendiente de atención desde hace más de <strong>{{ $horasPendiente }} horas</strong>.
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
            <span class="ticket-label">Estado Actual:</span>
            <span class="ticket-val">
                <span class="badge badge-{{ strtolower($ticket->status) }}">{{ strtoupper($ticket->status) }}</span>
            </span>
        </div>
        <div class="ticket-row">
            <span class="ticket-label">Prioridad:</span>
            <span class="ticket-val">
                <span class="badge badge-{{ strtolower($ticket->priority) }}">{{ strtoupper($ticket->priority) }}</span>
            </span>
        </div>
        <div class="ticket-row">
            <span class="ticket-label">Técnico Asignado:</span>
            <span class="ticket-val">{{ $ticket->assignee?->name ?? 'Sin asignar' }}</span>
        </div>
        <div class="ticket-row">
            <span class="ticket-label">Última Actualización:</span>
            <span class="ticket-val">{{ $ticket->fecha_actualizacion ? $ticket->fecha_actualizacion->format('d/m/Y H:i') : $ticket->fecha_creacion->format('d/m/Y H:i') }}</span>
        </div>
    </div>

    <p class="message" style="font-size: 13px; color: #475569;">
        Por favor atiende o actualiza el estado de este caso para cumplir con los acuerdos de nivel de servicio (SLA).
    </p>

    <div class="btn-wrapper">
        <a href="{{ $actionUrl }}" class="btn" target="_blank">Atender Ticket Ahora</a>
    </div>
@endsection
