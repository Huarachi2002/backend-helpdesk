<?php
namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;

class LogAccessMiddleware
{
    // Rutas a excluir del log de accesos (para no saturar la bitácora)
    protected array $exclude = [
        'api/notifications',
        'api/notifications/unread-count',
        'api/me',
    ];

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $path = $request->path();
        foreach ($this->exclude as $excl) {
            if (str_starts_with($path, $excl)) return $response;
        }

        // Solo registrar si el usuario está autenticado y la respuesta es exitosa (2xx)
        if ($request->user() && $response->getStatusCode() < 400) {
            AuditLog::registrar('ACCESS', $request->user()->id, [
                'descripcion' => 'Acceso a recurso: ' . $path,
            ]);
        }

        return $response;
    }
}
