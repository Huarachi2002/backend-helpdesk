<?php

namespace App\Http\Controllers;

use App\Models\BotInteraction;
use Illuminate\Http\Request;

class BotInteractionController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'usuario_ci'                 => 'nullable|exists:usuarios,ci',
            'user_id'                    => 'nullable|exists:usuarios,ci',
            'intencion'                  => 'required_without:intent|string',
            'intent'                     => 'required_without:intencion|string',
            'consulta'                   => 'required_without:query|string',
            'query'                      => 'required_without:consulta|string',
            'respuesta'                  => 'required_without:response|string',
            'response'                   => 'required_without:respuesta|string',
            'resuelto'                   => 'required_without:resolved|boolean',
            'resolved'                   => 'required_without:resuelto|boolean',
            'tiempo_resolucion_segundos' => 'required_without:resolution_time_seconds|integer',
            'resolution_time_seconds'    => 'required_without:tiempo_resolucion_segundos|integer',
        ]);

        $interaction = BotInteraction::create([
            'usuario_ci'                 => $validated['usuario_ci'] ?? $validated['user_id'] ?? null,
            'intencion'                  => $validated['intencion'] ?? $validated['intent'],
            'consulta'                   => $validated['consulta'] ?? $validated['query'],
            'respuesta'                  => $validated['respuesta'] ?? $validated['response'],
            'resuelto'                   => $validated['resuelto'] ?? $validated['resolved'],
            'tiempo_resolucion_segundos' => $validated['tiempo_resolucion_segundos'] ?? $validated['resolution_time_seconds'],
        ]);

        return response()->json(['message' => 'Interacción registrada exitosamente.', 'data' => $interaction], 201);
    }

    public function index(Request $request)
    {
        $query = BotInteraction::query();

        $query->when($request->has('resolved') || $request->has('resuelto'), function ($q) use ($request) {
            $val = $request->has('resuelto') ? $request->boolean('resuelto') : $request->boolean('resolved');
            $q->where('resuelto', $val);
        })
        ->when($request->input('intencion', $request->input('intent')), fn ($q, $intent) => $q->where('intencion', $intent))
        ->when($request->date_from, fn ($q, $date_from) => $q->whereDate('fecha_creacion', '>=', $date_from))
        ->when($request->date_to, fn ($q, $date_to) => $q->whereDate('fecha_creacion', '<=', $date_to));

        return response()->json($query->orderBy('fecha_creacion', 'desc')->paginate(15));
    }
}
