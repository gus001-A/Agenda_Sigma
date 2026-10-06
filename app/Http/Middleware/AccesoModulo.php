<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Verifica el acceso del rol al módulo según la matriz "Usuarios y permisos". */
class AccesoModulo
{
    public function handle(Request $request, Closure $next, string $modulo): Response
    {
        $user = $request->user();
        if (! $user || ! $user->canModule($modulo)) {
            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json(['message' => 'Tu rol no tiene acceso a este módulo.'], 403);
            }

            return redirect()->route('inicio')->with('error', 'Tu rol no tiene acceso a ese módulo.');
        }

        return $next($request);
    }
}
