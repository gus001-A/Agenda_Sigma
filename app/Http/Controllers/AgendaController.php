<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Services\Agenda;
use App\Support\Presentador;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgendaController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $hoy = now()->format('Y-m-d');
        $fecha = $request->date('fecha')?->format('Y-m-d') ?? $hoy;
        $modo = in_array($request->modo, ['dia', 'semana', 'mes'], true) ? $request->modo : 'dia';
        $f = [
            'spec' => $request->integer('spec') ?: null,
            'doc' => $user->isDoctor() ? $user->doctor_id : ($request->integer('doc') ?: null),
            'room' => $request->integer('room') ?: null,
            'st' => $request->string('st')->toString() ?: null,
            'type' => $request->string('type')->toString() ?: null,
        ];
        $medicos = Doctor::with('specialty:id,name', 'room:id,name')->where('active', true)
            ->when($f['spec'], fn ($q) => $q->where('specialty_id', $f['spec']))
            ->when($f['doc'], fn ($q) => $q->where('id', $f['doc']))
            ->when($f['room'], fn ($q) => $q->where('resource_id', $f['room']))
            ->orderBy('name')->get();

        $info = fn (Doctor $d) => ['id' => $d->id, 'name' => $d->name, 'specialty' => $d->specialty->name, 'room' => $d->room?->name,
            'days' => $d->days, 'start' => $d->start_time, 'end' => $d->end_time, 'duration' => $d->duration];
        $datos = [];

        if ($modo === 'dia') {
            $agenda = new Agenda($fecha, $fecha);
            $datos['columnas'] = $medicos->filter(fn ($d) => $d->worksOn($fecha) || count($agenda->citasDe($d->id, $fecha)))
                ->map(fn ($d) => ['doctor' => $info($d), 'slots' => array_map([Presentador::class, 'espacio'], $agenda->espacios($d, $fecha))])->values();
        } elseif ($modo === 'semana') {
            $d = $medicos->first();
            $lunes = Carbon::parse($fecha)->startOfWeek(Carbon::MONDAY)->format('Y-m-d');
            $agenda = new Agenda($lunes, Agenda::sumarDias($lunes, 6));
            $datos['doctor'] = $d ? $info($d) : null;
            $datos['columnas'] = $d ? collect(range(0, 6))->map(function ($i) use ($d, $lunes, $agenda) {
                $dia = Agenda::sumarDias($lunes, $i);

                return ['date' => $dia, 'works' => $d->worksOn($dia), 'slots' => array_map([Presentador::class, 'espacio'], $agenda->espacios($d, $dia))];
            }) : [];
        } else {
            $primero = Carbon::parse($fecha)->startOfMonth();
            $inicio = $primero->copy()->startOfWeek(Carbon::MONDAY)->format('Y-m-d');
            $agenda = new Agenda($inicio, Agenda::sumarDias($inicio, 41));
            $datos['celdas'] = collect(range(0, 41))->map(function ($i) use ($inicio, $medicos, $agenda, $primero, $f) {
                $dia = Agenda::sumarDias($inicio, $i);
                $n = $libres = $tot = 0;
                foreach ($medicos as $d) {
                    foreach ($agenda->espacios($d, $dia) as $s) {
                        if ($s['off'] && ! $s['appt']) {
                            continue;
                        }
                        $tot++;
                        if ($s['appt'] && (! $f['st'] || $s['state'] === $f['st']) && (! $f['type'] || $s['appt']->type === $f['type'])) {
                            $n++;
                        }
                        if ($s['state'] === 'Disponible') {
                            $libres++;
                        }
                    }
                }

                return ['date' => $dia, 'in' => (int) substr($dia, 5, 2) === $primero->month, 'citas' => $n, 'libres' => $libres, 'total' => $tot];
            });
        }

        return Inertia::render('Agenda', [
            'fecha' => $fecha,
            'modo' => $modo,
            'filtros' => $f,
            'bloqueado' => $user->isDoctor(),
            'ahora' => now()->hour * 60 + now()->minute,
        ] + $datos);
    }
}
