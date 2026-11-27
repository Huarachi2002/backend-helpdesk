@extends('emails.layout')

@section('content')
    <div class="greeting">¡Hola, {{ $recipientName }}!</div>

    @if($esSolicitante)
        <p class="message">
            Te informamos que tu ticket <strong>{{ $ticket->codigo }}</strong> fue derivado a otro nivel de atención
            para su resolución. Seguimos trabajando en tu solicitud y te notificaremos cualquier novedad.
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
                <span class="ticket-label">Fecha:</span>
                <span class="ticket-val">{{ now()->format('d/m/Y H:i') }}</span>
            </div>
        </div>
    @else
        <p class="message">
            @if($esDevolucion)
                <strong>{{ $actorName }}</strong> te ha <strong>devuelto</strong> el ticket <strong>{{ $ticket->codigo }}</strong>
                para que continúes con su atención.
            @elseif($tipo === 'RELEVAMIENTO_ASCENDENTE')
                <strong>{{ $actorName }}</strong> ha <strong>relevado</strong> el ticket <strong>{{ $ticket->codigo }}</strong>
                a tu cargo (nivel superior). Ahora eres el responsable de su atención.
            @else
                <strong>{{ $actorName }}</strong> ha <strong>relevado</strong> el ticket <strong>{{ $ticket->codigo }}</strong>
                a tu cargo (nivel inferior). Ahora eres el responsable de su atención.
            @endif
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
            @if($cargo)
                <div class="ticket-row">
                    <span class="ticket-label">Cargo / Nivel:</span>
                    <span class="ticket-val" style="color: #b45309;">{{ $cargo->nombre }} (Nivel {{ $cargo->nivel }})</span>
                </div>
            @endif
            <div class="ticket-row">
                <span class="ticket-label">Realizado por:</span>
                <span class="ticket-val">{{ $actorName }}</span>
            </div>
            <div class="ticket-row">
                <span class="ticket-label">Fecha:</span>
                <span class="ticket-val">{{ now()->format('d/m/Y H:i') }}</span>
            </div>
        </div>

        @if(!empty($motivo))
            <div style="background-color: #fffbeb; border-left: 4px solid #d97706; padding: 12px 16px; border-radius: 4px; font-size: 13px; margin-bottom: 20px;">
                <strong style="color: #b45309;">{{ $esDevolucion ? 'Instrucciones:' : 'Motivo del Relevamiento:' }}</strong><br>
                {{ $motivo }}
            </div>
        @endif
    @endif

    <div class="btn-wrapper">
        <a href="{{ $actionUrl }}" class="btn" target="_blank">Ver Detalles del Ticket</a>
    </div>
@endsection
