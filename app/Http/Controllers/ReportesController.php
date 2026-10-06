<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Reservation;
use App\Models\Resource;
use App\Services\Agenda;
use App\Support\Catalogo;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Reportes de agenda, recursos, pacientes, anticipos e historias clínicas. */
class ReportesController extends Controller
{
    private function rango(Request $request): array
    {
        $desde = $request->date('desde')?->format('Y-m-d') ?? now()->startOfMonth()->format('Y-m-d');
        $hasta = $request->date('hasta')?->format('Y-m-d') ?? now()->endOfMonth()->format('Y-m-d');

        return $desde <= $hasta ? [$desde, $hasta] : [$hasta, $desde];
    }

    public function index(Request $request): Response
    {
        [$desde, $hasta] = $this->rango($request);
        $hoy = now()->format('Y-m-d');
        $L = Appointment::with('doctor:id,name,resource_id,duration', 'specialty:id,name', 'patient:id,name')->whereBetween('date', [$desde, $hasta])->get();
        $act = $L->where('status', '!=', 'Reagendada');

        $agrupar = fn (string $campo, callable $nombre) => $act->groupBy($campo)->map(fn ($g, $k) => [
            'l' => $nombre($g->first()), 'v' => $g->count(),
            'atendidas' => $g->where('status', 'Atendida')->count(), 'canceladas' => $g->where('status', 'Cancelada')->count(),
        ])->sortByDesc('v')->values();

        $porEstado = $L->countBy('status')->sortDesc();
        $noAtendidas = $L->filter(fn ($a) => $a->dateString() < $hoy && in_array($a->status, ['Agendada', 'Confirmada', 'Anticipo pendiente'], true))->count();

        // Ocupación de recursos (reservaciones + consultas en consultorios)
        $dias = Carbon::parse($desde)->diffInDays(Carbon::parse($hasta)) + 1;
        $reservas = Reservation::where('status', 'Programada')->whereBetween('date', [$desde, $hasta])->get();
        $ocupacion = Resource::whereIn('type', ['Consultorio', 'Quirófano', 'Sala de procedimientos'])->orderBy('id')->get()->map(function ($r) use ($dias, $reservas, $act) {
            $disp = (Agenda::min($r->closes_at) - Agenda::min($r->opens_at)) * $dias;
            $usado = $reservas->where('resource_id', $r->id)->sum('duration');
            if ($r->type === 'Consultorio') {
                $usado += $act->where('status', '!=', 'Cancelada')->filter(fn ($a) => $a->doctor->resource_id === $r->id)->sum('duration');
            }

            return ['l' => $r->name, 'v' => $disp ? min(100, (int) round($usado * 100 / $disp)) : 0];
        });

        // Horas de agenda médica: ocupadas, libres y bloqueadas
        $libre = $bloq = $ocup = 0;
        $agenda = new Agenda($desde, min($hasta, Agenda::sumarDias($desde, 61)));
        $medicos = Doctor::all();
        for ($i = 0; $i < min($dias, 62); $i++) {
            $f = Agenda::sumarDias($desde, $i);
            foreach ($medicos as $d) {
                foreach ($agenda->espacios($d, $f) as $s) {
                    if ($s['off']) {
                        continue;
                    }
                    if ($s['state'] === 'Bloqueada') {
                        $bloq += $s['dur'];
                    } elseif ($s['appt']) {
                        $ocup += $s['dur'];
                    } else {
                        $libre += $s['dur'];
                    }
                }
            }
        }

        $dep = $L->where('deposit', '>', 0);
        $porPaciente = $act->groupBy('patient_id')->map(fn ($g) => ['l' => $g->first()->patient->name, 'v' => $g->count()])->sortByDesc('v')->take(5)->values();
        $hc = $act->where('status', '!=', 'Cancelada')->countBy('hc_status');

        return Inertia::render('Reportes', [
            'desde' => $desde, 'hasta' => $hasta,
            'kpis' => [
                'citas' => $act->count(), 'atendidas' => $porEstado['Atendida'] ?? 0, 'canceladas' => $porEstado['Cancelada'] ?? 0,
                'reagendadas' => $porEstado['Reagendada'] ?? 0, 'noAtendidas' => $noAtendidas,
                'pacientesAtendidos' => $act->where('status', 'Atendida')->pluck('patient_id')->unique()->count(),
                'pacientesNuevos' => $act->where('type', 'Primera consulta')->where('status', '!=', 'Cancelada')->pluck('patient_id')->unique()->count(),
            ],
            'porMedico' => $agrupar('doctor_id', fn ($a) => $a->doctor->name),
            'porEspecialidad' => $agrupar('specialty_id', fn ($a) => $a->specialty->name),
            'porEstado' => $porEstado->map(fn ($v, $k) => ['l' => $k, 'v' => $v])->values(),
            'ocupacion' => $ocupacion,
            'horas' => ['ocupadas' => round($ocup / 60), 'libres' => round($libre / 60), 'bloqueadas' => round($bloq / 60)],
            'anticipos' => [
                'cobrados' => $dep->where('refunded', false)->sum('paid'),
                'pendientes' => $dep->filter->isActive()->sum(fn ($a) => max(0, $a->deposit - $a->paid)),
                'reembolsados' => $dep->where('refunded', true)->sum('paid'),
            ],
            'historias' => collect(Catalogo::ESTADOS_HC)->map(fn ($k) => ['l' => $k, 'v' => $hc[$k] ?? 0]),
            'topPacientes' => $porPaciente,
        ]);
    }

    /** Exportación CSV (compatible con Excel) de las citas del periodo. */
    public function exportar(Request $request): StreamedResponse
    {
        [$desde, $hasta] = $this->rango($request);

        return response()->streamDownload(function () use ($desde, $hasta) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Fecha', 'Hora', 'ID SIGMA24', 'Paciente', 'Médico', 'Especialidad', 'Tipo', 'Motivo', 'Estado', 'Historia clínica', 'Costo', 'Anticipo requerido', 'Anticipo pagado', 'Estado anticipo']);
            Appointment::with('patient:id,name,sigma_id', 'doctor:id,name', 'specialty:id,name')->whereBetween('date', [$desde, $hasta])
                ->orderBy('date')->orderBy('time')->chunk(500, function ($citas) use ($out) {
                    foreach ($citas as $a) {
                        fputcsv($out, [$a->dateString(), $a->time, $a->patient->sigma_id, $a->patient->name, $a->doctor->name, $a->specialty->name, $a->type,
                            $a->reason, $a->status, $a->hc_status, $a->cost, $a->deposit, $a->paid, $a->depositState()]);
                    }
                });
            fclose($out);
        }, "citas_{$desde}_{$hasta}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
