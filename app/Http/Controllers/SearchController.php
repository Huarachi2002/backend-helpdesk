<?php
namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use App\Models\Categoria;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        $q = trim($request->get('q', ''));
        if (strlen($q) < 2) {
            return response()->json(['tickets' => [], 'usuarios' => [], 'categorias' => [], 'query' => $q]);
        }

        $user = $request->user();
        $isAdmin = $user->role === 'admin';
        $isSupport = $user->role === 'support';

        // Buscar Tickets
        $ticketsQuery = Ticket::with(['categorias', 'prioridad'])
            ->where(function($q2) use ($q) {
                $q2->where('asunto', 'ilike', "%{$q}%")
                   ->orWhere('descripcion', 'ilike', "%{$q}%")
                   ->orWhere('codigo', 'ilike', "%{$q}%");
            })
            ->limit(8);

        // Si es técnico (no admin), solo ver sus tickets asignados o creados
        if (!$isAdmin) {
            $ticketsQuery->where(function($q2) use ($user) {
                $q2->where('tecnico_asignado_id', $user->id)
                   ->orWhere('solicitante_id', $user->id);
            });
        }

        $tickets = $ticketsQuery->get()->map(function($t) {
            return [
                'id'     => $t->id,
                'codigo' => $t->codigo,
                'asunto' => $t->asunto,
                'estado' => $t->estado,
                'type'   => 'ticket',
                'url'    => "/tickets/{$t->id}",
            ];
        });

        // Buscar Usuarios (solo admin)
        $usuarios = collect();
        if ($isAdmin) {
            $usuarios = User::with('rol')
                ->where(function($q2) use ($q) {
                    $q2->where('nombre', 'ilike', "%{$q}%")
                       ->orWhere('apellido', 'ilike', "%{$q}%")
                       ->orWhere('correo', 'ilike', "%{$q}%");
                })
                ->limit(5)
                ->get()
                ->map(function($u) {
                    return [
                        'id'     => $u->id,
                        'nombre' => $u->nombre . ' ' . $u->apellido,
                        'correo' => $u->correo,
                        'rol'    => $u->rol?->nombre,
                        'type'   => 'usuario',
                        'url'    => "/users",
                    ];
                });
        }

        // Buscar Categorías (admin y soporte)
        $categorias = collect();
        if ($isAdmin || $isSupport) {
            $categorias = Categoria::where('nombre', 'ilike', "%{$q}%")
                ->orWhere('descripcion', 'ilike', "%{$q}%")
                ->limit(5)
                ->get()
                ->map(function($c) {
                    return [
                        'id'     => $c->id,
                        'nombre' => $c->nombre,
                        'type'   => 'categoria',
                        'url'    => "/categories",
                    ];
                });
        }

        return response()->json([
            'query'      => $q,
            'tickets'    => $tickets,
            'usuarios'   => $usuarios,
            'categorias' => $categorias,
        ]);
    }
}
