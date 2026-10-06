<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Block;
use App\Models\Doctor;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\WaitlistEntry;
use App\Services\Agenda;
use App\Support\Catalogo;
use App\Support\Presentador;
use Inertia\Inertia;
use Inertia\Response;

class PanelController extends Controller
{
    public function __invoke(): Response
    {
        $hoy = now()->format('Y-m-d');
        $agenda = new Agenda($hoy, $hoy);
        $nm = $agenda->minutoActual();

        $lista = Appointment::with('patient:id,name', 'doctor:id,name', 'specialty:id,name')->where('date', $hoy)->where('status', '!=', 'Reagendada')->get();
        $act = $lista->where('status', '!=', 'Cancelada');
        $fut = Appointment::active()->where('date', '>=', $hoy)->where('status', '!=', 'Atendida')->get(['id', 'date', 'deposit', 'paid', 'refunded', 'hc_status', 'status']);
        $antP = $fut->filter(fn ($a) => in_array($a->depositState(), ['Pendiente', 'Parcial'], true));
        $hasta7 = Agenda::sumarDias($hoy, 7);
        $hcP = $fut->filter(fn ($a) => $a->dateString() <= $hasta7 && in_array($a->hc_status, ['Pendiente', 'En proceso', 'Requiere actualización'], true));

        $medicos = Doctor::where('active', true)->get();
        $consultorios = Resource::where('type', 'Consultorio')->where('active', true)->get();
        $occC = $consultorios->filter(fn ($r) => $medicos->contains(fn ($d) => $d->resource_id === $r->id && $d->worksOn($hoy)
            && Agenda::min($d->start_time) <= $nm && $nm < Agenda::min($d->end_time)))->count();
        $quirofanos = Resource::where('type', 'Quirófano')->where('active', true)->get();
        $resHoy = Reservation::where('date', $hoy)->where('status', 'Programada')->get();
        $occQ = $quirofanos->filter(fn ($r) => $resHoy->contains(fn ($x) => $x->resource_id === $r->id
            && Agenda::min($x->start_time) <= $nm && $nm < Agenda::min($x->start_time) + $x->duration))->count();

        $proximas = $act->filter(fn ($a) => $a->status !== 'Atendida' && Agenda::min($a->time) + 30 >= $nm)->sortBy('time')->take(15)
            ->map(fn ($a) => Presentador::cita($a))->values();

        $hoyMedicos = $medicos->filter(fn ($d) => $d->worksOn($hoy))->sortBy('name');
        $cargas = $hoyMedicos->map(function ($d) use ($agenda, $hoy) {
            $s = collect($agenda->espacios($d, $hoy))->where('off', false);

            return ['l' => $d->name, 'v' => $s->whereNotNull('appt')->count(), 'bloq' => $s->where('state', 'Bloqueada')->count(), 'tot' => $s->count()];
        })->values();

        $semana = Appointment::active()->whereBetween('date', [$hoy, $hasta7])->get(['hc_status']);
        $hc = collect(Catalogo::ESTADOS_HC)->mapWithKeys(fn ($k) => [$k => $semana->where('hc_status', $k)->count()]);

        return Inertia::render('Panel', [
            'fecha' => $hoy,
            'medicosHoy' => $hoyMedicos->count(),
            'bloqueosHoy' => Block::where('scope', 'doctor')->get()->filter(fn ($b) => $b->appliesOn($hoy))->count(),
            'kpis' => [
                'citas' => $act->count(),
                'confirmadas' => $act->where('status', 'Confirmada')->count(),
                'pendientes' => $act->whereIn('status', ['Agendada', 'Anticipo pendiente'])->count(),
                'canceladas' => $lista->where('status', 'Cancelada')->count(),
                'pacientes' => $act->pluck('patient_id')->unique()->count(),
                'anticiposMonto' => $antP->sum(fn ($a) => $a->deposit - $a->paid),
                'anticiposCitas' => $antP->count(),
                'historias' => $hcP->count(),
                'consultorios' => "{$occC}/{$consultorios->count()}",
                'quirofanos' => "{$occQ}/{$quirofanos->count()}",
            ],
            'proximas' => $proximas,
            'cargas' => $cargas,
            'historias' => $hc,
            'estadosHoy' => $lista->countBy('status'),
            'espera' => WaitlistEntry::with('patient:id,name', 'specialty:id,name')->where('status', 'En espera')->limit(4)->get()
                ->map(fn ($w) => ['patient' => $w->patient->name, 'specialty' => $w->specialty->name, 'priority' => $w->priority]),
        ]);
    }
}
