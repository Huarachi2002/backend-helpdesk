@extends('emails.layout')

@section('content')
    <div class="greeting">¡Hola, {{ $recipientName }}!</div>
    <p class="message">
        Se ha registrado un nuevo ticket en el sistema con el código <strong>{{ $ticket->codigo }}</strong>.
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
            <span class="ticket-label">Prioridad:</span>
            <span class="ticket-val">
                <span class="badge badge-{{ strtolower($ticket->priority) }}">{{ strtoupper($ticket->priority) }}</span>
            </span>
        </div>
        <div class="ticket-row">
            <span class="ticket-label">Categoría:</span>
            <span class="ticket-val">{{ $ticket->category?->nombre ?? 'General' }}</span>
        </div>
        <div class="ticket-row">
            <span class="ticket-label">Solicitante:</span>
            <span class="ticket-val">{{ $ticket->creator?->name ?? 'Usuario' }}</span>
        </div>
        <div class="ticket-row">
            <span class="ticket-label">Técnico Asignado:</span>
            <span class="ticket-val">{{ $ticket->assignee?->name ?? 'Sin asignar (En espera)' }}</span>
        </div>
        <div class="ticket-row">
            <span class="ticket-label">Fecha de Registro:</span>
            <span class="ticket-val">{{ $ticket->fecha_creacion ? $ticket->fecha_creacion->format('d/m/Y H:i') : now()->format('d/m/Y H:i') }}</span>
        </div>
    </div>

    <div style="background-color: #f1f5f9; padding: 12px 16px; border-radius: 6px; font-size: 13px; margin-bottom: 20px;">
        <strong>Descripción:</strong><br>
        {{ $ticket->descripcion }}
    </div>

    <div class="btn-wrapper">
        <a href="{{ $actionUrl }}" class="btn" target="_blank">Ver Ticket en la Plataforma</a>
    </div>
@endsection
