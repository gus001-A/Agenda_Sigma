<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\LimpiaDatosSigma;
use App\Models\Block;
use App\Models\Doctor;
use App\Models\Specialty;
use App\Models\User;
use App\Services\Agenda;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Copia los médicos activos de la base heredada de SIGMA (usuarios con
 * acceso MEDICO) y las especialidades que usan.
 *
 * Es idempotente por `legacy_id`. SIGMA guarda un horario por día y la agenda
 * uno por médico: se toma el rango más amplio y los días más cortos se
 * recortan con bloqueos recurrentes, que se regeneran en cada corrida.
 * Costo, duración y consultorio sólo se fijan al crear el médico.
 */
class ImportarMedicosSigma extends Command
{
    use LimpiaDatosSigma;

    protected $signature = 'sigma:importar-medicos';

    protected $description = 'Importa los médicos activos y sus especialidades de la base heredada de SIGMA (sigma2024)';

    private const ACCESO_MEDICO = 2;

    /** Especialidad para los médicos que en SIGMA no tienen una (es "LICENCIATURA EN MEDICINA"). */
    private const ESPECIALIDAD_GENERAL = 1;

    /** Columnas de horario de SIGMA → día de la semana de la agenda (0 = domingo). */
    private const DIAS = ['do' => 0, 'lu' => 1, 'ma' => 2, 'mi' => 3, 'ju' => 4, 'vi' => 5, 'sa' => 6];

    /** Títulos de `titulos` que se respetan; el resto (incluido Doctor/Doctora) queda como Dr./Dra. según el sexo. */
    private const TITULOS = [9 => 'Lic.', 13 => 'Psic.', 21 => 'Odont.', 24 => 'Psiq.'];

    private const AUTOR = 'Importación SIGMA';

    public function handle(): int
    {
        $origen = DB::connection('sigma');
        $medicos = $origen->table('usuarios')->where('activo_u', 1)->where('acceso_u', self::ACCESO_MEDICO)->orderBy('id_u')->get();
        $this->info("Importando {$medicos->count()} médicos de ".$origen->getDatabaseName().'…');

        $ids = $medicos->map(fn ($m) => $m->especialidad_u ?: self::ESPECIALIDAD_GENERAL)->unique();
        $especialidades = $origen->table('especialidades')->whereIn('id_es', $ids)->get()
            ->mapWithKeys(fn ($e) => [$e->id_es => $this->especialidad($e)]);

        $creados = 0;
        DB::transaction(function () use ($medicos, $especialidades, &$creados) {
            foreach ($medicos as $m) {
                $creados += $this->medico($m, $especialidades[$m->especialidad_u ?: self::ESPECIALIDAD_GENERAL]) ? 1 : 0;
            }
        });

        $this->table(['Especialidad', 'Médicos de SIGMA'], $especialidades->map(fn (Specialty $e) => [$e->name, $e->doctors()->whereNotNull('legacy_id')->count()]));
        $this->info("Listo. {$creados} médicos nuevos, ".($medicos->count() - $creados).' actualizados. Médicos en la agenda: '.Doctor::count());

        return self::SUCCESS;
    }

    /** Liga la especialidad de SIGMA con la de la agenda del mismo nombre, o la crea. */
    private function especialidad(object $e): Specialty
    {
        $nombre = $e->id_es === self::ESPECIALIDAD_GENERAL ? 'Medicina General' : $this->nombreEspecialidad($e->nombre_especialidad);
        $especialidad = Specialty::where('legacy_id', $e->id_es)->first()
            ?? Specialty::whereNull('legacy_id')->get()->first(fn (Specialty $s) => Str::lower(Str::ascii($s->name)) === Str::lower(Str::ascii($nombre)))
            ?? new Specialty(['name' => $nombre]);
        $especialidad->legacy_id = $e->id_es;
        $especialidad->created_by ??= self::AUTOR;
        $especialidad->save();

        return $especialidad;
    }

    /** "GINECOLOGIA Y OBSTETRICIA" → "Ginecología y Obstetricia". */
    private function nombreEspecialidad(string $s): string
    {
        $s = $this->propio($this->texto($s));
        $s = preg_replace(['/logia\b/u', '/iatria\b/u', '/\bCirugia\b/u'], ['logía', 'iatría', 'Cirugía'], $s);

        return preg_replace_callback('/(?<=\s)(Y|De|Del|La)\b/u', fn ($x) => mb_strtolower($x[1]), $s);
    }

    /** Crea o actualiza el médico; devuelve true si es nuevo. */
    private function medico(object $m, Specialty $especialidad): bool
    {
        $horario = $this->horario($m);
        $medico = Doctor::firstOrNew(['legacy_id' => $m->id_u]);
        $nuevo = ! $medico->exists;
        $medico->fill([
            'name' => $this->nombre($m),
            'specialty_id' => $especialidad->id,
            'phone' => $this->telefono($m->tCelular_u) ?? $this->telefono($m->tTrabajo_u) ?? $this->telefono($m->tParticular_u),
            'days' => $horario['dias'],
            'start_time' => $horario['inicio'],
            'end_time' => $horario['fin'],
            'notes' => implode(' · ', array_filter([
                ($c = $this->texto($m->cedulaProfesional_u)) !== '' ? "Cédula {$c}" : null,
                $horario['dias'] ? null : 'Sin horario en SIGMA',
            ])) ?: null,
        ]);
        if ($nuevo) {
            $medico->fill(['cost' => $especialidad->default_cost ?? 0, 'duration' => 30]);
            $medico->created_by = self::AUTOR;
        }
        $medico->save();

        $this->recortes($medico, $horario['recortes']);
        $this->usuario($medico, $m);

        return $nuevo;
    }

    private function nombre(object $m): string
    {
        $titulo = self::TITULOS[$m->id_titulo_u] ?? ((int) $m->sexo_u === 1 ? 'Dra.' : 'Dr.');
        $completo = trim(implode(' ', array_filter([$this->texto($m->nombre_u), $this->texto($m->apaterno_u), $this->texto($m->amaterno_u)])));

        return $titulo.' '.$this->propio($completo);
    }

    /**
     * Rango más amplio de la semana y, por cada día que empieza más tarde o
     * termina antes, el tramo que hay que bloquear.
     *
     * @return array{dias: int[], inicio: string, fin: string, recortes: array<string, int[]>}
     */
    private function horario(object $m): array
    {
        $rangos = [];
        foreach (self::DIAS as $col => $dia) {
            $a = substr((string) $m->{"horario_e_{$col}"}, 0, 5);
            $b = substr((string) $m->{"horario_s_{$col}"}, 0, 5);
            // 00:00 a 00:00 (o 00:00:01 a 00:00:02) significa que no consulta ese día.
            if ($a !== '' && $b !== '' && Agenda::min($b) - Agenda::min($a) >= 30) {
                $rangos[$dia] = [$a, $b];
            }
        }
        ksort($rangos);
        if (! $rangos) {
            return ['dias' => [], 'inicio' => '08:00', 'fin' => '14:00', 'recortes' => []];
        }

        $inicio = min(array_column($rangos, 0));
        $fin = max(array_column($rangos, 1));
        $recortes = [];
        foreach ($rangos as $dia => [$a, $b]) {
            if ($a > $inicio) {
                $recortes["{$inicio}-{$a}"][] = $dia;
            }
            if ($b < $fin) {
                $recortes["{$b}-{$fin}"][] = $dia;
            }
        }

        return ['dias' => array_keys($rangos), 'inicio' => $inicio, 'fin' => $fin, 'recortes' => $recortes];
    }

    /** @param  array<string, int[]>  $recortes */
    private function recortes(Doctor $medico, array $recortes): void
    {
        Block::where('scope', 'doctor')->where('target_id', $medico->id)->where('created_by', self::AUTOR)->delete();
        foreach ($recortes as $tramo => $dias) {
            [$a, $b] = explode('-', $tramo);
            Block::create(['scope' => 'doctor', 'target_id' => $medico->id, 'type' => 'Fin de agenda', 'date' => now()->format('Y-m-d'),
                'start_time' => $a, 'end_time' => $b, 'recurring' => true, 'days' => $dias, 'notes' => 'Horario de SIGMA', 'created_by' => self::AUTOR]);
        }
    }

    /** Cada médico tiene un usuario para consultar su agenda (igual que al darlo de alta en Catálogos). */
    private function usuario(Doctor $medico, object $m): void
    {
        $usuario = User::firstOrNew(['doctor_id' => $medico->id]);
        $usuario->name = $medico->name;
        if (! $usuario->exists) {
            $correo = $this->correo($m->email_u);
            $usuario->fill([
                'email' => $correo && ! User::where('email', $correo)->exists() ? $correo : 'medico'.$medico->id.'.'.Str::lower(Str::random(4)).'@clinicasanantonio.mx',
                'role' => 'Médico', 'password' => Hash::make(Str::random(16)),
            ]);
            $usuario->created_by = self::AUTOR;
        }
        $usuario->save();
    }
}
