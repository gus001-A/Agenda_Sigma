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
        $nm = (new Agenda($hoy, $hoy))->minutoActual();

        $lista = Appointment::with('patient:id,name,sigma_id', 'doctor:id,name', 'specialty:id,name')->where('date', $hoy)->where('status', '!=', 'Reagendada')->get();
        $act = $lista->where('status', '!=', 'Cancelada');
        $fut = Appointment::active()->where('date', '>=', $hoy)->where('status', '!=', 'Atendida')->get(['id', 'date', 'deposit', 'paid', 'refunded', 'hc_status', 'status']);
        $antP = $fut->filter(fn ($a) => in_array($a->depositState(), ['Pendiente', 'Parcial'], true));

        $medicos = Doctor::where('active', true)->get();
        $consultorios = Resource::where('type', 'Consultorio')->where('active', true)->get();
        $occC = $consultorios->filter(fn ($r) => $medicos->contains(fn ($d) => $d->resource_id === $r->id && $d->worksOn($hoy)
            && Agenda::min($d->start_time) <= $nm && $nm < Agenda::min($d->end_time)))->count();
        $quirofanos = Resource::where('type', 'Quirófano')->where('active', true)->get();
        $resHoy = Reservation::where('date', $hoy)->where('status', 'Programada')->get();
        $occQ = $quirofanos->filter(fn ($r) => $resHoy->contains(fn ($x) => $x->resource_id === $r->id
            && Agenda::min($x->start_time) <= $nm && $nm < Agenda::min($x->start_time) + $x->duration))->count();

        $proximas = $act->filter(fn ($a) => $a->status !== 'Atendida' && Agenda::min($a->time) + 30 >= $nm)->sortBy('time')->take(30)
            ->map(fn ($a) => Presentador::cita($a))->values();

        $hoyMedicos = $medicos->filter(fn ($d) => $d->worksOn($hoy));

        // Lista de espera: primero la prioridad alta y, dentro de cada prioridad, quien lleva más tiempo esperando.
        $espera = WaitlistEntry::with('patient:id,name,phone', 'specialty:id,name', 'doctor:id,name')->where('status', 'En espera')
            ->orderByRaw("FIELD(priority, 'Alta', 'Media', 'Baja')")->orderBy('requested_at')->get();

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
                'consultorios' => "{$occC}/{$consultorios->count()}",
                'quirofanos' => "{$occQ}/{$quirofanos->count()}",
            ],
            'proximas' => $proximas,
            'estadosHoy' => $lista->countBy('status'),
            'esperaTotal' => $espera->count(),
            'espera' => $espera->take(6)->map(fn ($w) => [
                'patient' => $w->patient->name, 'phone' => $w->patient->phone, 'specialty' => $w->specialty->name, 'doctor' => $w->doctor?->name,
                'priority' => $w->priority, 'preference' => $w->preference, 'days' => (int) $w->requested_at->diffInDays(now()->startOfDay()),
            ])->values(),
        ]);
    }
}
