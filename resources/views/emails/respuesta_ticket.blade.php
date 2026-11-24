@extends('emails.layout')

@section('content')
    <div class="greeting">¡Hola, {{ $recipientName }}!</div>
    <p class="message">
        @if($esSoporte)
            El equipo de soporte respondió tu ticket <strong>{{ $ticket->codigo }}</strong>.
        @else
            <strong>{{ $authorName }}</strong> respondió el ticket <strong>{{ $ticket->codigo }}</strong> que tienes asignado.
        @endif
    </p>

    <div style="background-color: #f8fafc; border-left: 4px solid #2563eb; padding: 14px 18px; border-radius: 4px; font-size: 14px; line-height: 1.6; color: #1e293b; margin-bottom: 20px;">
        <div style="font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 6px;">
            {{ $authorName }} · {{ now()->format('d/m/Y H:i') }}
        </div>
        {!! nl2br(e($cuerpo)) !!}
    </div>

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
            <span class="ticket-label">Estado actual:</span>
            <span class="ticket-val">
                <span class="badge badge-{{ $ticket->status }}">{{ $statusLabel }}</span>
            </span>
        </div>
    </div>

    <div class="btn-wrapper">
        <a href="{{ $actionUrl }}" class="btn" target="_blank">Ver y responder en el sistema</a>
    </div>
@endsection

@section('footer_note')
