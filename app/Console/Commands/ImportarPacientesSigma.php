<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\LimpiaDatosSigma;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Copia los pacientes de la base heredada de SIGMA (conexión "sigma",
 * tabla `pacientes`) a la tabla `patients` de la agenda.
 *
 * Es idempotente: el expediente original queda en `legacy_id` y el
 * identificador visible es SG-{id_p}, así que se puede volver a correr
 * para traer pacientes nuevos o actualizar los existentes.
 */
class ImportarPacientesSigma extends Command
{
    use LimpiaDatosSigma;

    protected $signature = 'sigma:importar-pacientes {--todos : Incluir pacientes no vigentes}';

    protected $description = 'Importa los pacientes de la base heredada de SIGMA (sigma2024)';

    private const SEXOS = [1 => 'Femenino', 2 => 'Masculino'];

    public function handle(): int
    {
        $origen = DB::connection('sigma');
        $consulta = $origen->table('pacientes')->where('activo_p', 1)
            ->when(! $this->option('todos'), fn ($q) => $q->where('vigente', 1));

        $total = (clone $consulta)->count();
        $this->info("Importando {$total} pacientes de ".$origen->getDatabaseName().'…');

        // Los pacientes de demostración que ocupen un SG-#### de la base real se renombran.
        $this->liberarIdentificadores();

        $barra = $this->output->createProgressBar($total);
        $ahora = now()->format('Y-m-d H:i:s');

        $consulta->orderBy('id_p')->select(['id_p', 'curp_p', 'nombre_p', 'apaterno_p', 'amaterno_p', 'sexo_p', 'fNac_p',
            'tCelular_p', 'tParticular_p', 'tTrabajo_p', 'email_p', 'fechaR_p'])
            ->chunk(2000, function ($filas) use ($barra, $ahora) {
                $registros = $filas->map(fn ($p) => [
                    'sigma_id' => 'SG-'.$p->id_p,
                    'legacy_id' => $p->id_p,
                    'name' => $this->nombre($p),
                    'curp' => $this->curp($p->curp_p),
                    'sex' => self::SEXOS[$p->sexo_p] ?? null,
                    'birth_date' => $this->fecha($p->fNac_p),
                    'phone' => $this->telefono($p->tCelular_p) ?? $this->telefono($p->tParticular_p) ?? $this->telefono($p->tTrabajo_p),
                    'email' => $this->correo($p->email_p),
                    'created_at' => $this->fechaHora($p->fechaR_p) ?? $ahora,
                    'created_by' => 'Importación SIGMA',
                    'updated_at' => $ahora,
                ])->all();

                DB::table('patients')->upsert($registros, ['legacy_id'],
                    ['sigma_id', 'name', 'curp', 'sex', 'birth_date', 'phone', 'email', 'updated_at']);
                $barra->advance(count($registros));
            });

        $barra->finish();
        $this->newLine(2);
        $this->info('Listo. Pacientes en la agenda: '.DB::table('patients')->count());

        return self::SUCCESS;
    }

    private function liberarIdentificadores(): void
    {
        $demo = DB::table('patients')->whereNull('legacy_id')->where('sigma_id', 'like', 'SG-%')->pluck('sigma_id', 'id');
        foreach ($demo as $id => $sigma) {
            DB::table('patients')->where('id', $id)->update(['sigma_id' => 'DEMO-'.substr($sigma, 3)]);
        }
    }

    private function nombre(object $p): string
    {
        $completo = trim(implode(' ', array_filter([$this->texto($p->nombre_p), $this->texto($p->apaterno_p), $this->texto($p->amaterno_p)])));

        return $this->propio($completo) ?: 'Sin nombre';
    }

    private function curp(?string $c): ?string
    {
        $c = mb_strtoupper(str_replace(' ', '', $this->texto($c)), 'UTF-8');

        return preg_match('/^[A-ZÑ0-9]{16,18}$/u', $c) ? $c : null;
    }

    private function fecha(?string $f): ?string
    {
        $f = substr((string) $f, 0, 10);

        return $this->valida($f) && $f >= '1900-01-01' && $f <= now()->format('Y-m-d') ? $f : null;
    }

    private function fechaHora(?string $f): ?string
    {
        return $f && $this->valida($f) && $f >= '2000-01-01' ? substr($f, 0, 19) : null;
    }

    /** SIGMA guarda fechas incompletas como 1976-11-00. */
    private function valida(string $f): bool
    {
        [$a, $m, $d] = array_map('intval', explode('-', substr($f, 0, 10)) + [0, 0, 0]);

        return checkdate($m, $d, $a);
    }
}
