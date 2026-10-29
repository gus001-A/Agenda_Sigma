<?php

namespace App\Http\Controllers;

use App\Exceptions\ReglaNegocio;
use App\Models\Appointment;
use App\Models\Block;
use App\Models\Doctor;
use App\Models\Reservation;
use App\Models\Resource;
use App\Services\Agenda;
use App\Services\Auditoria;
use App\Services\Espacios;
use App\Support\Catalogo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Quirófanos y espacios (línea de tiempo), reservaciones y bloqueos de agenda. */
class EspaciosController extends Controller
{
    public function index(Request $request): Response
    {
        $fecha = $request->date('fecha')?->format('Y-m-d') ?? now()->format('Y-m-d');
        $tipo = $request->string('tipo')->toString() ?: null;
        $agenda = new Agenda($fecha, $fecha);
        $medicos = Doctor::where('active', true)->get();
        $reservas = Reservation::with('doctor:id,name', 'patient:id,name')->where('date', $fecha)->where('status', 'Programada')->get();

        $filas = Resource::when($tipo, fn ($q) => $q->where('type', $tipo))->orderBy('id')->get()->map(function (Resource $r) use ($fecha, $medicos, $reservas, $agenda) {
            $items = [];
            if (in_array($r->type, ['Consultorio', 'Sala de rehabilitación'], true)) {
                foreach ($medicos->filter(fn ($d) => $d->resource_id === $r->id && $d->worksOn($fecha)) as $d) {
                    $items[] = ['kind' => 'shift', 'start' => Agenda::min($d->start_time), 'end' => Agenda::min($d->end_time), 'label' => "Consulta: {$d->name}"];
                }
            }
            foreach ($reservas->where('resource_id', $r->id) as $x) {
                $s = Agenda::min($x->start_time);
                $items[] = ['kind' => 'res', 'id' => $x->id, 'start' => $s, 'end' => $s + $x->duration, 'label' => "{$x->start_time} {$x->procedure}",
                    'sub' => trim(($x->doctor?->name ?? '').($x->patient ? ', '.$x->patient->name : ''), ', ')];
            }
            foreach ($agenda->bloqueosEn('resource', $r->id, $fecha) as $b) {
                $items[] = ['kind' => 'blk', 'id' => $b->id, 'start' => Agenda::min($b->start_time), 'end' => Agenda::min($b->end_time), 'label' => $b->type];
            }

            return ['id' => $r->id, 'name' => $r->name, 'type' => $r->type, 'location' => $r->location, 'active' => $r->active,
                'opens' => Agenda::min($r->opens_at), 'closes' => Agenda::min($r->closes_at), 'items' => $items];
        });

        return Inertia::render('Espacios', [
            'fecha' => $fecha,
            'tipo' => $tipo,
            'filas' => $filas,
            'ahora' => $agenda->minutoActual(),
        ]);
    }

    // ---------------- reservaciones ----------------

    private function datosReserva(Request $request): array
    {
        return $request->validate([
            'resource_id' => 'required|exists:resources,id', 'date' => 'required|date_format:Y-m-d', 'start_time' => 'required|date_format:H:i',
            'duration' => 'required|integer|min:15|max:1440', 'doctor_id' => 'nullable|exists:doctors,id', 'patient_id' => 'nullable|exists:patients,id',
            'procedure' => 'required|string|max:255', 'notes' => 'nullable|string|max:255',
        ], [], ['procedure' => 'procedimiento o actividad', 'duration' => 'duración']);
    }

    public function verReserva(Reservation $reservacion): JsonResponse
    {
        $reservacion->load('resource', 'doctor:id,name', 'patient:id,name');

        return response()->json($reservacion->toArray() + ['date_s' => $reservacion->date->format('Y-m-d'),
            'end_time' => Agenda::hhmm(Agenda::min($reservacion->start_time) + $reservacion->duration)]);
    }

    public function guardarReserva(Request $request, ?Reservation $reservacion = null): JsonResponse
    {
        $v = $this->datosReserva($request);
        $checks = Espacios::validar($v, $reservacion?->id);
        if (collect($checks)->contains('ok', false)) {
            throw new ReglaNegocio(collect($checks)->firstWhere('ok', false)['t']);
        }
        $nombre = Resource::find($v['resource_id'])->name;
        if ($reservacion?->exists) {
            $reservacion->update($v);
            Auditoria::log('Reservación editada', "{$nombre}: {$v['procedure']}", pacienteId: $v['patient_id'] ?? null);
        } else {
            Reservation::create($v + ['status' => 'Programada', 'created_by' => $request->user()->name]);
            Auditoria::log('Reservación creada', "{$nombre}, ".Catalogo::fechaCorta($v['date'])." {$v['start_time']}: {$v['procedure']}", pacienteId: $v['patient_id'] ?? null);
        }

        return response()->json(['ok' => true, 'message' => 'Espacio reservado']);
    }

    public function cancelarReserva(Reservation $reservacion): JsonResponse
    {
        $reservacion->update(['status' => 'Cancelada']);
        Auditoria::log('Reservación cancelada', "{$reservacion->resource->name}: {$reservacion->procedure}", pacienteId: $reservacion->patient_id);

        return response()->json(['ok' => true, 'message' => 'Reservación cancelada']);
    }

    // ---------------- bloqueos ----------------

    public function verBloqueo(Block $bloqueo): JsonResponse
    {
        return response()->json($bloqueo->resumen());
    }

    public function guardarBloqueo(Request $request): JsonResponse
    {
        $v = $request->validate([
            'scope' => ['required', Rule::in(['doctor', 'resource'])], 'target_id' => 'nullable|integer',
            'type' => ['required', Rule::in(Catalogo::TIPOS_BLOQUEO)], 'date' => 'required|date_format:Y-m-d',
            'all_day' => 'boolean', 'start_time' => 'required_unless:all_day,true|nullable|date_format:H:i', 'end_time' => 'required_unless:all_day,true|nullable|date_format:H:i',
            'recurring' => 'boolean', 'days' => 'array', 'days.*' => 'integer|between:0,6', 'until' => 'nullable|date_format:Y-m-d|after_or_equal:date',
            'notes' => 'nullable|string|max:255',
        ]);
        if ($v['scope'] === 'resource' && empty($v['target_id'])) {
            throw new ReglaNegocio('Elige el espacio a bloquear.');
        }
        $s = ! empty($v['all_day']) ? '00:00' : $v['start_time'];
        $e = ! empty($v['all_day']) ? '23:59' : $v['end_time'];
        if (Agenda::min($s) >= Agenda::min($e)) {
            throw new ReglaNegocio('La hora final debe ser posterior a la inicial.');
        }
        $rec = ! empty($v['recurring']);
        if ($rec && empty($v['days'])) {
            throw new ReglaNegocio('Elige al menos un día de la semana.');
        }
        $b = new Block(['scope' => $v['scope'], 'target_id' => $v['target_id'] ?: null, 'type' => $v['type'], 'date' => $v['date'],
            'start_time' => $s, 'end_time' => $e, 'recurring' => $rec, 'days' => $rec ? array_values($v['days']) : [],
            'until' => $rec ? ($v['until'] ?? null) : null, 'notes' => $v['notes'] ?? null, 'created_by' => $request->user()->name]);

        // Regla: no se puede bloquear sobre citas o reservaciones existentes.
        $conflictos = [];
        $hasta = $rec ? ($b->until?->format('Y-m-d') ?? Agenda::sumarDias($v['date'], 119)) : $v['date'];
        $hasta = min($hasta, Agenda::sumarDias($v['date'], 119));
        if ($v['scope'] === 'doctor') {
            $citas = Appointment::active()->with('patient:id,name')->where('status', '!=', 'Atendida')->whereBetween('date', [$v['date'], $hasta])
                ->when($b->target_id, fn ($q) => $q->where('doctor_id', $b->target_id))->get();
            foreach ($citas as $a) {
                if ($b->appliesOn($a->dateString()) && Agenda::traslapa(Agenda::min($a->time), Agenda::min($a->time) + $a->duration, Agenda::min($s), Agenda::min($e))) {
                    $conflictos[] = Catalogo::fechaCorta($a->dateString())." {$a->time}, {$a->patient->name}";
                }
            }
        } else {
            $res = Reservation::where('status', 'Programada')->where('resource_id', $b->target_id)->whereBetween('date', [$v['date'], $hasta])->get();
            foreach ($res as $r) {
                if ($b->appliesOn($r->date->format('Y-m-d')) && Agenda::traslapa(Agenda::min($r->start_time), Agenda::min($r->start_time) + $r->duration, Agenda::min($s), Agenda::min($e))) {
                    $conflictos[] = Catalogo::fechaCorta($r->date->format('Y-m-d'))." {$r->start_time}, {$r->procedure}";
                }
            }
        }
        if ($conflictos) {
            return response()->json(['message' => 'Hay '.count($conflictos).' '.($v['scope'] === 'doctor' ? 'citas' : 'reservaciones').' en ese periodo. Reagéndalas o cancélalas antes de bloquear.',
                'conflictos' => array_slice($conflictos, 0, 10)], 422);
        }
        $b->save();
        Auditoria::log('Bloqueo creado', $b->targetName().": {$b->type} {$s} a {$e}".($rec ? ' (semanal)' : ''));

        return response()->json(['ok' => true, 'message' => 'Horario bloqueado']);
    }

    public function eliminarBloqueo(Block $bloqueo): JsonResponse
    {
        Auditoria::log('Bloqueo eliminado', $bloqueo->targetName().": {$bloqueo->type} {$bloqueo->start_time} a {$bloqueo->end_time}");
        $bloqueo->delete();

        return response()->json(['ok' => true, 'message' => 'Bloqueo eliminado. Los espacios quedaron disponibles.']);
    }
}
