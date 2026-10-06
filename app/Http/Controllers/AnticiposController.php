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
        $todas = Appointment::with('patient:id,name', 'doctor:id,name')->where('deposit', '>', 0)->where('status', '!=', 'Reagendada')
            ->orderBy('date')->orderBy('time')->get();
        $lista = $todas->filter(fn ($a) => (! $estado || $a->depositState() === $estado) && $a->dateString() >= Agenda::sumarDias($hoy, -10))
            ->take(150)->map(fn ($a) => Presentador::cita($a))->values();

        return Inertia::render('Anticipos', [
            'estado' => $estado,
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
