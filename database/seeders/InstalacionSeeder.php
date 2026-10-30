<?php

namespace Database\Seeders;

use App\Models\ClinicalForm;
use App\Models\ReminderRule;
use App\Models\RoleModule;
use App\Models\Setting;
use App\Models\Specialty;
use App\Support\Catalogo;
use Illuminate\Database\Seeder;

/**
 * Lo mínimo para arrancar el sistema sin datos de demostración: permisos por rol,
 * canales, reglas de recordatorio, especialidades, formatos clínicos y el administrador.
 *
 *   php artisan db:seed --class=InstalacionSeeder
 */
class InstalacionSeeder extends Seeder
{
    /** Especialidades y formatos creados, por clave (e1…, f1…), para DatabaseSeeder. */
    public array $especialidades = [];

    public array $formatos = [];

    public string $publicadoPor = 'Instalación inicial';

    public function run(): void
    {
        foreach (Catalogo::PERMISOS_DEFAULT as $rol => $mods) {
            foreach ($mods as $m) {
                RoleModule::firstOrCreate(['role' => $rol, 'module' => $m]);
            }
        }
        Setting::put('canales', array_fill_keys(Catalogo::CANALES, true));
        ReminderRule::create(['code' => 'r72', 'hours' => 72, 'active' => true, 'only_if_hc_pending' => true, 'text' => 'Le recordamos completar su información clínica antes de su cita.']);
        ReminderRule::create(['code' => 'r24', 'hours' => 24, 'active' => true, 'only_if_hc_pending' => false, 'text' => 'Su cita es mañana. Aún puede completar su historia clínica.']);
        ReminderRule::create(['code' => 'r0', 'hours' => 0, 'active' => true, 'only_if_hc_pending' => false, 'text' => 'Su cita está programada para hoy.']);

        // ---------- especialidades ----------
        $E = [];
        $formsEsp = [];
        foreach ([
            'e1' => ['Traumatología', 'Lesiones y enfermedades del sistema músculo-esquelético', 800, 'f1', 'f2'],
            'e2' => ['Ginecología', 'Salud ginecológica y reproductiva', 900, 'f3', 'f6'],
            'e3' => ['Neurología', 'Sistema nervioso central y periférico', 1100, 'f4', 'f6'],
            'e4' => ['Cardiología', 'Corazón y sistema circulatorio', 1000, 'f5', 'f6'],
            'e5' => ['Pediatría', 'Atención de niñas, niños y adolescentes', 700, 'f5', 'f6'],
            'e6' => ['Dermatología', 'Piel, pelo y uñas', 750, 'f5', 'f6'],
            'e7' => ['Fisioterapia', 'Rehabilitación física', 500, 'f5', 'f6'],
            'e8' => ['Psicología', 'Salud mental y acompañamiento psicológico', 650, 'f5', 'f6'],
        ] as $k => [$n, $d, $c, $f1, $f2]) {
            $E[$k] = Specialty::create(['name' => $n, 'description' => $d, 'default_cost' => $c]);
            $formsEsp[$k] = [$f1, $f2];
        }

        // ---------- formatos clínicos ----------
        $F = [];
        foreach (PlantillasFormatos::todas() as $k => $def) {
            $F[$k] = ClinicalForm::create(['name' => $def['name'], 'specialty_id' => $def['spec'] ? $E[$def['spec']]->id : null, 'consult_type' => $def['type'], 'active' => true]);
            foreach ($def['versions'] as $v) {
                $F[$k]->versions()->create(['version' => $v['v'], 'notes' => $v['notes'], 'sections' => $v['sections'], 'published_at' => $v['date'], 'published_by' => $this->publicadoPor]);
            }
        }
        foreach ($E as $k => $e) {
            $e->update(['first_form_id' => $F[$formsEsp[$k][0]]->id, 'sub_form_id' => $F[$formsEsp[$k][1]]->id]);
        }

        [$this->especialidades, $this->formatos] = [$E, $F];

        $this->call(AdministradorSeeder::class);
    }
}
