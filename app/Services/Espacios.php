<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Reservation;
use App\Models\Resource;

/** Validación de reservaciones de quirófanos, consultorios y otros espacios. */
class Espacios
{
    /**
     * Revisa disponibilidad del recurso, horario permitido, otras reservaciones, bloqueos,
     * consultas asignadas al espacio y disponibilidad del médico.
     *
     * @return array<int, array{ok:bool, t:string}>
     */
    public static function validar(array $r, ?int $propia = null): array
    {
        $R = Resource::find($r['resource_id'] ?? null);
        $fecha = $r['date'];
        $s = Agenda::min($r['start_time'] ?? '00:00');
        $e = $s + (int) ($r['duration'] ?? 0);
        $agenda = new Agenda($fecha, $fecha);
        $c = [];

        $c[] = ['ok' => (bool) $R?->active, 't' => 'El recurso está activo'];
        $c[] = ['ok' => $R && $s >= Agenda::min($R->opens_at) && $e <= Agenda::min($R->closes_at),
            't' => 'Dentro del horario permitido'.($R ? " ({$R->opens_at} a {$R->closes_at})" : '')];

        $otras = Reservation::where('status', 'Programada')->where('date', $fecha)->when($propia, fn ($q) => $q->where('id', '!=', $propia))->get();
        $otra = $otras->first(fn ($x) => $x->resource_id === (int) $r['resource_id'] && Agenda::traslapa($s, $e, Agenda::min($x->start_time), Agenda::min($x->start_time) + $x->duration));
        $c[] = ['ok' => ! $otra, 't' => $otra ? "Ya existe una reservación: {$otra->start_time} {$otra->procedure}" : 'Sin otra reservación en ese horario'];

        $bl = collect($agenda->bloqueosEn('resource', (int) $r['resource_id'], $fecha))
            ->first(fn ($b) => Agenda::traslapa($s, $e, Agenda::min($b->start_time), Agenda::min($b->end_time)));
        $c[] = ['ok' => ! $bl, 't' => $bl ? 'Bloqueado por '.mb_strtolower($bl->type)." ({$bl->start_time} a {$bl->end_time})" : 'Sin bloqueos del recurso'];

        $turno = $R && $R->type === 'Consultorio'
            ? Doctor::where('resource_id', $R->id)->get()->first(fn ($d) => $d->worksOn($fecha) && $d->id !== (int) ($r['doctor_id'] ?? 0)
                && Agenda::traslapa($s, $e, Agenda::min($d->start_time), Agenda::min($d->end_time)))
            : null;
        $c[] = ['ok' => ! $turno, 't' => $turno ? "El consultorio está asignado a la consulta de {$turno->name}" : 'Sin conflicto con consultas asignadas al espacio'];

        if (! empty($r['doctor_id'])) {
            $d = Doctor::find($r['doctor_id']);
            $cita = collect($agenda->citasDe($d->id, $fecha))->first(fn (Appointment $a) => Agenda::traslapa($s, $e, Agenda::min($a->time), Agenda::min($a->time) + $a->duration));
            $bloq = collect($agenda->bloqueosEn('doctor', $d->id, $fecha))->first(fn ($b) => $b->type !== 'Procedimiento' && Agenda::traslapa($s, $e, Agenda::min($b->start_time), Agenda::min($b->end_time)));
            $orr = $otras->first(fn ($x) => $x->doctor_id === $d->id && Agenda::traslapa($s, $e, Agenda::min($x->start_time), Agenda::min($x->start_time) + $x->duration));
            $c[] = ['ok' => ! $cita && ! $bloq && ! $orr, 't' => $cita ? "{$d->name} tiene consulta a las {$cita->time}"
                : ($bloq ? "{$d->name} tiene bloqueo: {$bloq->type}" : ($orr ? "{$d->name} está en otra reservación ({$orr->start_time})" : "{$d->name} está disponible"))];
        }

        return $c;
    }
}
