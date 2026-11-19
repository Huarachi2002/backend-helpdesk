<?php

namespace App\Http\Controllers;

use App\Models\Notificacion;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Lista las notificaciones del usuario autenticado.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Notificacion::where('usuario_id', $user->id)
            ->with(['ticket.prioridad', 'ticket.categorias'])
            ->orderBy('fecha_creacion', 'desc');

        if ($request->boolean('unread_only')) {
            $query->unread();
        }

        $limit = min((int) ($request->get('limit', 20)), 100);
        $notifications = $query->paginate($limit);
        $unreadCount = Notificacion::where('usuario_id', $user->id)->unread()->count();

        return response()->json([
            'unread_count' => $unreadCount,
            'total'        => $notifications->total(),
            'current_page' => $notifications->currentPage(),
            'last_page'    => $notifications->lastPage(),
            'data'         => $notifications->items(),
        ]);
    }

    /**
     * Retorna únicamente el conteo de notificaciones no leídas (optimizado para polling frecuente).
     */
    public function unreadCount(Request $request)
    {
        $user = $request->user();
        $count = Notificacion::where('usuario_id', $user->id)
            ->unread()
            ->count();

        return response()->json([
            'unread_count' => $count
        ]);
    }

    /**
     * Marca una notificación específica como leída.
     */
    public function markAsRead(Request $request, $id)
    {
        $user = $request->user();
        $notificacion = Notificacion::where('usuario_id', $user->id)->findOrFail($id);

        if (!$notificacion->leida) {
            $notificacion->update([
                'leida'       => true,
                'fecha_leida' => now(),
            ]);
        }

        $unreadCount = Notificacion::where('usuario_id', $user->id)->unread()->count();

        return response()->json([
            'message'      => 'Notificación marcada como leída.',
            'unread_count' => $unreadCount,
            'data'         => $notificacion
        ]);
    }

    /**
     * Marca todas las notificaciones pendientes del usuario como leídas.
     */
    public function markAllAsRead(Request $request)
    {
        $user = $request->user();

        Notificacion::where('usuario_id', $user->id)
            ->where('leida', false)
            ->update([
                'leida'       => true,
                'fecha_leida' => now(),
            ]);

        return response()->json([
            'message'      => 'Todas las notificaciones han sido marcadas como leídas.',
            'unread_count' => 0
        ]);
    }

    /**
     * Elimina una notificación de la bandeja.
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $notificacion = Notificacion::where('usuario_id', $user->id)->findOrFail($id);
        $notificacion->delete();

        $unreadCount = Notificacion::where('usuario_id', $user->id)->unread()->count();

        return response()->json([
            'message'      => 'Notificación eliminada.',
            'unread_count' => $unreadCount
        ]);
    }
}
