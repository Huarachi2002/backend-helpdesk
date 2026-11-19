@extends('emails.layout')

@section('content')
    <div class="greeting">¡Hola, {{ $recipientName }}!</div>
    <p class="message">
        Te informamos que el ticket <strong>{{ $ticket->codigo }}</strong> ha cambiado de estado.
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
            <span class="ticket-label">Estado Anterior:</span>
            <span class="ticket-val">
                <span class="badge badge-{{ strtolower($oldStatus) }}">{{ strtoupper($oldStatus) }}</span>
            </span>
        </div>
        <div class="ticket-row">
            <span class="ticket-label">Nuevo Estado:</span>
            <span class="ticket-val">
                <span class="badge badge-{{ strtolower($newStatus) }}">{{ strtoupper($newStatus) }}</span>
            </span>
        </div>
        <div class="ticket-row">
            <span class="ticket-label">Actualizado por:</span>
            <span class="ticket-val">{{ $changerName }}</span>
        </div>
        <div class="ticket-row">
            <span class="ticket-label">Fecha de Actualización:</span>
            <span class="ticket-val">{{ now()->format('d/m/Y H:i') }}</span>
        </div>
    </div>

    @if(!empty($comments))
        <div style="background-color: #f8fafc; border-left: 4px solid #2563eb; padding: 12px 16px; border-radius: 4px; font-size: 13px; margin-bottom: 20px;">
            <strong>Comentarios / Justificación:</strong><br>
            {{ $comments }}
        </div>
    @endif

    <div class="btn-wrapper">
        <a href="{{ $actionUrl }}" class="btn" target="_blank">Revisar Ticket en el Sistema</a>
    </div>
@endsection
