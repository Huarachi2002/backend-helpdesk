<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureIsStaff
{
    /**
     * Permite el acceso únicamente a usuarios con rol de Administrador o Técnico de Soporte.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || !in_array($request->user()->role, ['admin', 'support'], true)) {
            return response()->json([
                'message' => 'Acceso denegado. Este módulo requiere privilegios de Administrador o Técnico de Soporte.',
                'error'   => 'unauthorized_staff_only'
            ], 403);
        }

        return $next($request);
    }
}
