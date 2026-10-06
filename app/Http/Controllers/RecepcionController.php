<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Services\Agenda;
use App\Support\Presentador;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecepcionController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $fecha = $request->date('fecha')?->format('Y-m-d') ?? now()->format('Y-m-d');
        $doc = $request->integer('doc') ?: null;
        $ocultar = $request->boolean('ocultar');
        $agenda = new Agenda($fecha, $fecha);

        $filas = [];
        foreach (Doctor::with('specialty:id,name')->when($doc, fn ($q) => $q->where('id', $doc))->get() as $d) {
            foreach ($agenda->espacios($d, $fecha) as $s) {
                if ($ocultar && in_array($s['state'], ['Disponible', 'No disponible'], true)) {
                    continue;
                }
                $filas[] = Presentador::espacio($s) + ['doctor_id' => $d->id, 'doctor' => $d->name, 'specialty' => $d->specialty->name];
            }
        }
        usort($filas, fn ($a, $b) => [$a['m'], $a['doctor']] <=> [$b['m'], $b['doctor']]);

        $inactivas = Appointment::with('patient:id,name', 'doctor:id,name', 'events')->where('date', $fecha)
            ->whereIn('status', Appointment::INACTIVOS)->when($doc, fn ($q) => $q->where('doctor_id', $doc))->orderBy('time')->get()
            ->map(fn ($a) => Presentador::cita($a) + ['last' => $a->events->last()?->text]);

        return Inertia::render('Recepcion', [
            'fecha' => $fecha,
            'filtros' => ['doc' => $doc, 'ocultar' => $ocultar],
            'filas' => $filas,
            'inactivas' => $inactivas,
        ]);
    }
}
