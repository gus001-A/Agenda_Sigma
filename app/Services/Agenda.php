<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Block;
use App\Models\Doctor;
use App\Models\WaitlistEntry;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Generador automático de agenda.
 *
 * La disponibilidad se calcula a partir de las reglas de horario de cada médico,
 * las citas activas y los bloqueos (puntuales o semanales). Las citas se cargan
 * por rangos de fecha para evitar una consulta por día.
 */
class Agenda
{
    /** @var array<int, array<string, Appointment[]>> */
    private array $citas = [];

    /** @var array<string, bool> fechas ya cargadas */
    private array $cargadas = [];

    private ?Collection $bloqueos = null;

    private string $hoy;

    private int $ahora;

    public function __construct(?string $desde = null, ?string $hasta = null)
    {
        $this->hoy = now()->format('Y-m-d');
        $this->ahora = now()->hour * 60 + now()->minute;
        if ($desde) {
            $this->asegurar($desde, $hasta ?? $desde);
        }
    }

    // ---------------- utilidades de tiempo ----------------

    public static function min(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm) + [0, 0]);

        return $h * 60 + $m;
    }

    public static function hhmm(int $m): string
    {
        return sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
    }

    public static function traslapa(int $a1, int $a2, int $b1, int $b2): bool
    {
        return $a1 < $b2 && $b1 < $a2;
    }

    public static function sumarDias(string $fecha, int $n): string
    {
        return Carbon::parse($fecha)->addDays($n)->format('Y-m-d');
    }

    public static function diaSemana(string $fecha): int
    {
        return (int) date('w', strtotime($fecha));
    }

    public function hoy(): string
    {
        return $this->hoy;
    }

    public function minutoActual(): int
    {
        return $this->ahora;
    }

    public function esPasado(string $fecha, int $m): bool
    {
        return $fecha < $this->hoy || ($fecha === $this->hoy && $m < $this->ahora);
    }

    // ---------------- carga de datos ----------------

    /** Carga (una sola vez) las citas activas del rango indicado. */
    public function asegurar(string $desde, string $hasta): void
    {
        $faltan = [];
        for ($d = $desde; $d <= $hasta; $d = self::sumarDias($d, 1)) {
            if (! isset($this->cargadas[$d])) {
                $faltan[] = $d;
            }
        }
        if (! $faltan) {
            return;
        }
        $citas = Appointment::query()->active()->with('patient:id,name,sigma_id')
            ->whereBetween('date', [reset($faltan), end($faltan)])->get();
        foreach ($faltan as $d) {
            $this->cargadas[$d] = true;
        }
        foreach ($citas as $c) {
            $f = $c->dateString();
            if (in_array($f, $faltan, true)) {
                $this->citas[$c->doctor_id][$f][] = $c;
            }
        }
    }

    /** Olvida las citas cargadas (tras crear/cancelar dentro de la misma petición). */
    public function refrescar(): void
    {
        $this->citas = [];
        $this->cargadas = [];
        $this->bloqueos = null;
    }

    public function citasDe(int $doctorId, string $fecha): array
    {
        $this->asegurar($fecha, $fecha);

        return $this->citas[$doctorId][$fecha] ?? [];
    }

    public function bloqueos(): Collection
    {
        return $this->bloqueos ??= Block::all();
    }

    /** Bloqueos que aplican a un médico (o recurso) en una fecha. */
    public function bloqueosEn(string $scope, ?int $targetId, string $fecha): array
    {
        return $this->bloqueos()->filter(fn (Block $b) => $b->scope === $scope
            && ($b->target_id === null || $b->target_id === $targetId)
            && $b->appliesOn($fecha))->values()->all();
    }

    // ---------------- generación de espacios ----------------

    /**
     * Espacios del médico en una fecha.
     *
     * @return array<int, array{time:string,m:int,dur:int,state:string,appt:?Appointment,block:?Block,off:bool}>
     */
    public function espacios(Doctor $d, string $fecha): array
    {
        $out = [];
        $citas = $this->citasDe($d->id, $fecha);
        $bloqueos = $this->bloqueosEn('doctor', $d->id, $fecha);
        $usadas = [];
        if ($d->worksOn($fecha)) {
            $fin = self::min($d->end_time);
            for ($m = self::min($d->start_time); $m + $d->duration <= $fin; $m += $d->duration) {
                $t = self::hhmm($m);
                $cita = null;
                foreach ($citas as $c) {
                    if ($c->time === $t) {
                        $cita = $c;
                        $usadas[$c->id] = true;
                        break;
                    }
                }
                $bloqueo = null;
                if (! $cita) {
                    foreach ($bloqueos as $b) {
                        if (self::traslapa($m, $m + $d->duration, self::min($b->start_time), self::min($b->end_time))) {
                            $bloqueo = $b;
                            break;
                        }
                    }
                }
                $estado = $cita ? $cita->status : ($bloqueo ? 'Bloqueada' : ($this->esPasado($fecha, $m) ? 'No disponible' : 'Disponible'));
                $out[] = ['time' => $t, 'm' => $m, 'dur' => $d->duration, 'state' => $estado, 'appt' => $cita, 'block' => $bloqueo, 'off' => false];
            }
        }
        // Citas que quedaron fuera del horario vigente (p. ej. tras cambiar la configuración del médico).
        foreach ($citas as $c) {
            if (! isset($usadas[$c->id])) {
                $out[] = ['time' => $c->time, 'm' => self::min($c->time), 'dur' => $c->duration, 'state' => $c->status, 'appt' => $c, 'block' => null, 'off' => true];
            }
        }
        usort($out, fn ($a, $b) => $a['m'] <=> $b['m']);

        return $out;
    }

    /** Horarios disponibles (HH:MM) de un médico en una fecha. */
    public function disponibles(Doctor $d, string $fecha): array
    {
        return array_values(array_map(fn ($s) => $s['time'], array_filter($this->espacios($d, $fecha), fn ($s) => $s['state'] === 'Disponible' && ! $s['off'])));
    }

    /**
     * Búsqueda de próxima disponibilidad.
     *
     * @return array<int, array{date:string, doctor:Doctor, times:string[]}>
     */
    public function proximos(?int $especialidadId, ?int $doctorId, string $desde, int $dias = 60, string $preferencia = '', int $limite = 0): array
    {
        $desde = max($desde, $this->hoy);
        $medicos = Doctor::query()->with('specialty:id,name')->where('active', true)
            ->when($doctorId, fn ($q) => $q->where('id', $doctorId))
            ->when($especialidadId, fn ($q) => $q->where('specialty_id', $especialidadId))
            ->orderBy('name')->get();
        $this->asegurar($desde, self::sumarDias($desde, $dias - 1));
        $out = [];
        for ($i = 0; $i < $dias; $i++) {
            $fecha = self::sumarDias($desde, $i);
            foreach ($medicos as $d) {
                $libres = array_filter($this->espacios($d, $fecha), fn ($s) => $s['state'] === 'Disponible' && ! $s['off']
                    && ($preferencia === 'Mañana' ? $s['m'] < 840 : ($preferencia === 'Tarde' ? $s['m'] >= 840 : true)));
                if ($libres) {
                    $out[] = ['date' => $fecha, 'doctor' => $d, 'times' => array_values(array_map(fn ($s) => $s['time'], $libres))];
                    if ($limite && count($out) >= $limite) {
                        return $out;
                    }
                }
            }
        }

        return $out;
    }

    /** Pacientes de la lista de espera compatibles con un espacio liberado. */
    public static function candidatos(Doctor $d, string $fecha, string $hora): Collection
    {
        $m = self::min($hora);
        $prioridad = ['Alta' => 0, 'Media' => 1, 'Baja' => 2];

        return WaitlistEntry::query()->with('patient:id,name,sigma_id', 'specialty:id,name')
            ->where('status', 'En espera')->where('specialty_id', $d->specialty_id)
            ->where(fn ($q) => $q->whereNull('doctor_id')->orWhere('doctor_id', $d->id))
            ->get()
            ->filter(fn ($w) => $w->preference === 'Cualquier horario'
                || ($w->preference === 'Mañana' && $m < 840) || ($w->preference === 'Tarde' && $m >= 840))
            ->sortBy([fn ($a, $b) => $prioridad[$a->priority] <=> $prioridad[$b->priority], fn ($a, $b) => $a->requested_at <=> $b->requested_at])
            ->values();
    }
}
