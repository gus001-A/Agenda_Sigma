<?php

namespace App\Http\Controllers;

use App\Services\Agenda;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DisponibilidadController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $hoy = now()->format('Y-m-d');
        $f = [
            'spec' => $request->integer('spec') ?: null,
            'doc' => $request->integer('doc') ?: null,
            'from' => max($request->date('from')?->format('Y-m-d') ?? $hoy, $hoy),
            'pref' => in_array($request->pref, ['Mañana', 'Tarde'], true) ? $request->pref : 'Cualquier horario',
        ];
        $r = (new Agenda)->proximos($f['spec'], $f['doc'], $f['from'], 60, $f['pref'] === 'Cualquier horario' ? '' : $f['pref'], 8);

        return Inertia::render('Disponibilidad', [
            'filtros' => $f,
            'resultados' => array_map(fn ($x) => [
                'date' => $x['date'], 'doctor_id' => $x['doctor']->id, 'doctor' => $x['doctor']->name, 'specialty' => $x['doctor']->specialty->name,
                'duration' => $x['doctor']->duration, 'cost' => $x['doctor']->cost, 'times' => $x['times'],
            ], $r),
        ]);
    }
}
