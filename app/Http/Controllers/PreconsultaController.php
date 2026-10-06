<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Services\Auditoria;
use App\Services\Formatos;
use App\Support\Presentador;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Vista del médico: pacientes del día con su información clínica previa. */
class PreconsultaController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $u = $request->user();
        $fecha = $request->date('fecha')?->format('Y-m-d') ?? now()->format('Y-m-d');
        $doc = $u->isDoctor() ? Doctor::find($u->doctor_id) : (Doctor::find($request->integer('doc')) ?? Doctor::orderBy('name')->first());
        $lista = Appointment::active()->with('patient', 'files', 'form')->where('doctor_id', $doc?->id)->where('date', $fecha)->orderBy('time')->get();
        $sel = $lista->firstWhere('id', $request->integer('cita')) ?? $lista->first();
        $detalle = null;
        if ($sel) {
            Auditoria::log('Consulta de historia clínica', "{$sel->patient->name} desde preconsulta", $sel);
            $detalle = Presentador::cita($sel) + [
                'form' => $sel->form?->name, 'version' => $sel->form_version, 'attention_note' => $sel->attention_note,
                'resumen' => Formatos::resumen($sel), 'secciones' => Formatos::respuestas($sel),
                'anteriores' => Appointment::with('doctor:id,name')->where('patient_id', $sel->patient_id)->where('id', '!=', $sel->id)
                    ->where('date', '<', $fecha)->orderByDesc('date')->limit(5)->get()
                    ->map(fn ($x) => ['date' => $x->dateString(), 'doctor' => $x->doctor->name, 'reason' => $x->reason, 'status' => $x->status, 'note' => $x->attention_note]),
            ];
        }

        return Inertia::render('Preconsulta', [
            'fecha' => $fecha,
            'doctor' => $doc ? ['id' => $doc->id, 'name' => $doc->name, 'works' => $doc->worksOn($fecha)] : null,
            'bloqueado' => $u->isDoctor(),
            'lista' => $lista->map(fn ($a) => ['id' => $a->id, 'time' => $a->time, 'patient' => $a->patient->name, 'type' => $a->type, 'hc_status' => $a->hc_status]),
            'seleccion' => $detalle,
            'hoy' => now()->format('Y-m-d'),
        ]);
    }
}
