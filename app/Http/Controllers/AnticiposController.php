<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Services\Agenda;
use App\Support\Presentador;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnticiposController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $hoy = now()->format('Y-m-d');
        $estado = in_array($request->estado, ['Pendiente', 'Parcial', 'Pagado', 'Reembolsado'], true) ? $request->estado : null;
        // Rango de fechas de la cita (calendario); por omisión, los últimos 30 días en adelante.
        $fecha = fn ($k) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->input($k)) ? $request->input($k) : null;
        $desde = $request->has('desde') ? $fecha('desde') : Agenda::sumarDias($hoy, -30);
        $hasta = $fecha('hasta');

        $todas = Appointment::with('patient:id,name', 'doctor:id,name')->withMax('payments', 'paid_at')
            ->where('deposit', '>', 0)->where('status', '!=', 'Reagendada')->get();
        // Más recientes primero: el último movimiento es el último pago o, si no hay, el registro de la cita.
        $lista = $todas->filter(fn ($a) => (! $desde || $a->dateString() >= $desde) && (! $hasta || $a->dateString() <= $hasta))
            ->sortByDesc(fn ($a) => max((string) $a->payments_max_paid_at, (string) $a->created_at))
            ->take(600)->map(fn ($a) => Presentador::cita($a) + ['movimiento' => max((string) $a->payments_max_paid_at, (string) $a->created_at)])->values();

        return Inertia::render('Anticipos', [
            'estado' => $estado,
            'desde' => $desde,
            'hasta' => $hasta,
            'kpis' => [
                'cobrados' => $todas->where('refunded', false)->sum('paid'),
                'pendientes' => $todas->filter(fn ($a) => $a->isActive() && $a->dateString() >= $hoy)->sum(fn ($a) => max(0, $a->deposit - $a->paid)),
                'reembolsados' => $todas->where('refunded', true)->sum('paid'),
                'citas' => $todas->count(),
            ],
            'lista' => $lista,
        ]);
    }
}
