<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Permiso de acción por rol. Acepta varias acciones separadas por "|" (basta una). */
class PermisoAccion
{
    private const MENSAJES = [
        'cita' => 'Tu rol no permite registrar ni modificar citas.',
        'bloqueo' => 'Solo Administración y Coordinación pueden bloquear horarios.',
        'espacio' => 'Tu rol no permite reservar espacios.',
        'config' => 'Solo el Administrador puede modificar la configuración.',
        'operativo' => 'Tu rol no permite administrar espacios.',
        'clinico' => 'Solo el personal médico puede realizar esta acción clínica.',
        'anticipo' => 'Tu rol no permite registrar pagos.',
        'verclinico' => 'Tu rol no tiene acceso al contenido clínico.',
        'consulta' => 'Tu rol no permite cambiar el estado de la atención.',
    ];

    public function handle(Request $request, Closure $next, string $acciones): Response
    {
        $lista = explode('|', $acciones);
        $user = $request->user();
        foreach ($lista as $a) {
            if ($user?->can_($a)) {
                return $next($request);
            }
        }
        $msg = self::MENSAJES[$lista[0]] ?? 'No tienes permiso para esta acción.';

        return $request->expectsJson()
            ? response()->json(['message' => $msg], 403)
            : back()->with('error', $msg);
    }
}
