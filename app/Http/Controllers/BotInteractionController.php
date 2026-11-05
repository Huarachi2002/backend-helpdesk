<?php

namespace App\Http\Controllers;

use App\Models\BotInteraction;
use Illuminate\Http\Request;

class BotInteractionController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'intent' => 'required|string',
            'query' => 'required|string',
            'response' => 'required|string',
            'resolved' => 'required|boolean',
            'resolution_time_seconds' => 'required|integer',
        ]);

        $interaction = BotInteraction::create($validated);

        return response()->json(['message' => 'Interacción registrada exitosamente.', 'data' => $interaction], 201);
    }

    public function index(Request $request)
    {
        $query = BotInteraction::query();

        // Filtros básicos de auditoría
        $query->when($request->has('resolved'), fn($q) => $q->where('resolved', $request->boolean('resolved')))
              ->when($request->intent, fn($q, $intent) => $q->where('intent', $intent))
              ->when($request->date_from, fn($q, $date_from) => $q->whereDate('created_at', '>=', $date_from))
              ->when($request->date_to, fn($q, $date_to) => $q->whereDate('created_at', '<=', $date_to));

        return response()->json($query->orderBy('created_at', 'desc')->paginate(15));
    }
}