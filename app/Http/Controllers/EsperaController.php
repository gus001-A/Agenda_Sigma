<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\WaitlistEntry;
use App\Services\Agenda;
use App\Services\Auditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EsperaController extends Controller
{
    private const ORDEN_ESTADO = ['En espera' => 0, 'Contactado' => 1, 'Agendado' => 2, 'Descartado' => 3];

    private const ORDEN_PRIORIDAD = ['Alta' => 0, 'Media' => 1, 'Baja' => 2];

    public function index(): Response
    {
        $lista = WaitlistEntry::with('patient:id,name,sigma_id', 'specialty:id,name', 'doctor:id,name')->get()
            ->sortBy([fn ($a, $b) => self::ORDEN_ESTADO[$a->status] <=> self::ORDEN_ESTADO[$b->status],
                fn ($a, $b) => self::ORDEN_PRIORIDAD[$a->priority] <=> self::ORDEN_PRIORIDAD[$b->priority]])
            ->values()->map(fn ($w) => [
                'id' => $w->id, 'patient' => $w->patient->name, 'patient_id' => $w->patient_id, 'specialty_id' => $w->specialty_id,
                'specialty' => $w->specialty->name, 'doctor_id' => $w->doctor_id, 'doctor' => $w->doctor?->name,
                'requested_at' => $w->requested_at->format('Y-m-d'), 'preference' => $w->preference, 'priority' => $w->priority,
                'notes' => $w->notes, 'status' => $w->status, 'appointment_id' => $w->appointment_id,
            ]);

        return Inertia::render('Espera', ['lista' => $lista]);
    }

    public function store(Request $request): JsonResponse
    {
        $v = $request->validate([
            'patient_id' => 'required|exists:patients,id', 'specialty_id' => 'required|exists:specialties,id',
            'doctor_id' => 'nullable|exists:doctors,id', 'preference' => ['required', Rule::in(['Cualquier horario', 'Mañana', 'Tarde'])],
            'priority' => ['required', Rule::in(['Alta', 'Media', 'Baja'])], 'notes' => 'nullable|string|max:255',
        ], [], ['patient_id' => 'paciente']);
        $w = WaitlistEntry::create($v + ['requested_at' => now()->format('Y-m-d'), 'status' => 'En espera']);
        Auditoria::log('Lista de espera', $w->patient->name.' agregado', pacienteId: $w->patient_id);

        return response()->json(['ok' => true, 'message' => 'Paciente agregado a la lista de espera']);
    }

    public function estado(Request $request, WaitlistEntry $espera): JsonResponse
    {
        $v = $request->validate(['status' => ['required', Rule::in(['En espera', 'Contactado', 'Descartado'])]]);
        $espera->update($v);
        Auditoria::log('Lista de espera', "{$espera->patient->name}: {$v['status']}", pacienteId: $espera->patient_id);

        return response()->json(['ok' => true, 'message' => 'Estado actualizado']);
    }

    /** Espacios compatibles con la preferencia del paciente en los próximos 45 días. */
    public function espacios(WaitlistEntry $espera): JsonResponse
    {
        $r = (new Agenda)->proximos($espera->specialty_id, $espera->doctor_id, now()->format('Y-m-d'), 45,
            $espera->preference === 'Cualquier horario' ? '' : $espera->preference, 6);

        return response()->json(array_map(fn ($x) => ['date' => $x['date'], 'doctor_id' => $x['doctor']->id, 'doctor' => $x['doctor']->name, 'times' => array_slice($x['times'], 0, 10)], $r));
    }

    public function candidatos(Request $request): JsonResponse
    {
        $request->validate(['doctor_id' => 'required|exists:doctors,id', 'date' => 'required|date_format:Y-m-d', 'time' => 'required|date_format:H:i']);
        $c = Agenda::candidatos(Doctor::findOrFail($request->doctor_id), $request->date, $request->time);

        return response()->json($c->map(fn ($w) => ['id' => $w->id, 'patient_id' => $w->patient_id, 'patient' => $w->patient->name, 'notes' => $w->notes,
            'priority' => $w->priority, 'preference' => $w->preference, 'requested_at' => $w->requested_at->format('Y-m-d')]));
    }
}
