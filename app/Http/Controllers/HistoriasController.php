<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Services\Agenda;
use App\Services\Formatos;
use App\Support\Catalogo;
use App\Support\Presentador;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Seguimiento del llenado de historias clínicas (preconsulta). */
class HistoriasController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $hoy = now()->format('Y-m-d');
        $rango = in_array($request->rango, ['0', '3', '7', '14', 'todas'], true) ? $request->rango : '7';
        $estado = in_array($request->estado, Catalogo::ESTADOS_HC, true) ? $request->estado : null;
        $base = Appointment::active()->when($rango !== 'todas', fn ($q) => $q->whereBetween('date', [$hoy, Agenda::sumarDias($hoy, (int) $rango)]));
        $conteo = (clone $base)->selectRaw('hc_status, count(*) as n')->groupBy('hc_status')->pluck('n', 'hc_status');
        $lista = (clone $base)->with('patient:id,name', 'doctor:id,name', 'form', 'files:id,appointment_id,field_id')
            ->when($estado, fn ($q) => $q->where('hc_status', $estado))
            ->orderBy('date')->orderBy('time')->limit(150)->get()
            ->map(fn ($a) => Presentador::cita($a) + ['form' => $a->form?->name, 'version' => $a->form_version, 'progress' => Formatos::avance($a)]);

        return Inertia::render('Historias', [
            'rango' => $rango,
            'estado' => $estado,
            'conteo' => collect(Catalogo::ESTADOS_HC)->mapWithKeys(fn ($k) => [$k => (int) ($conteo[$k] ?? 0)]),
            'lista' => $lista,
        ]);
    }
}
