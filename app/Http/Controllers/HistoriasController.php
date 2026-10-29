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
        // Rango de fechas de la cita (calendario). Por omisión, de hoy a 7 días; "todas" quita el límite inicial.
        $fecha = fn ($k) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->input($k)) ? $request->input($k) : null;
        $desde = $request->has('desde') ? $fecha('desde') : $hoy;
        $hasta = $request->has('desde') ? $fecha('hasta') : Agenda::sumarDias($hoy, 7);
        $estado = in_array($request->estado, Catalogo::ESTADOS_HC, true) ? $request->estado : null;
        $base = Appointment::active()->when($desde, fn ($q, $v) => $q->where('date', '>=', $v))->when($hasta, fn ($q, $v) => $q->where('date', '<=', $v));
        $conteo = (clone $base)->selectRaw('hc_status, count(*) as n')->groupBy('hc_status')->pluck('n', 'hc_status');
        $lista = (clone $base)->with('patient:id,name', 'doctor:id,name', 'form', 'files:id,appointment_id,field_id')
            ->when($estado, fn ($q) => $q->where('hc_status', $estado))
            ->orderBy('date')->orderBy('time')->limit(600)->get()
            ->map(fn ($a) => Presentador::cita($a) + ['form' => $a->form?->name, 'version' => $a->form_version, 'progress' => Formatos::avance($a)]);

        return Inertia::render('Historias', [
            'desde' => $desde,
            'hasta' => $hasta,
            'estado' => $estado,
            'conteo' => collect(Catalogo::ESTADOS_HC)->mapWithKeys(fn ($k) => [$k => (int) ($conteo[$k] ?? 0)]),
            'lista' => $lista,
        ]);
    }
}
