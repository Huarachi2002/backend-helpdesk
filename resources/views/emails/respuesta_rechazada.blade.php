@extends('emails.layout')

@section('content')
    <div class="greeting">¡Hola, {{ $recipientName }}!</div>
    <p class="message">
        Recibimos tu correo, pero no pudimos agregarlo al ticket <strong>{{ $ticket->codigo }}</strong>
        porque ese ticket ya está <strong>{{ strtolower($statusLabel) }}</strong> y no admite más respuestas.
    </p>
    <p class="message">
        Si el problema continúa o tienes una nueva consulta, registra un nuevo ticket desde el sistema.
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
            <span class="ticket-label">Estado:</span>
            <span class="ticket-val">
                <span class="badge badge-{{ $ticket->status }}">{{ $statusLabel }}</span>
            </span>
        </div>
    </div>

    <div class="btn-wrapper">
        <a href="{{ $actionUrl }}" class="btn" target="_blank">Ir a la Mesa de Ayuda</a>
    </div>
@endsection
