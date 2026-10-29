<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Block;
use App\Models\ClinicalForm;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\ReminderRule;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\RoleModule;
use App\Models\Setting;
use App\Models\Specialty;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Services\Agenda;
use App\Services\Formatos;
use App\Services\Mensajeria;
use App\Support\Catalogo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Datos de demostración de Clínica San Antonio (basados en el prototipo AGENDA_SIGMA).
 * Las fechas son relativas al día en que se ejecuta el seeder.
 * Contraseña de todos los usuarios: demo1234
 */
class DatabaseSeeder extends Seeder
{
    private function r(): float
    {
        return mt_rand() / mt_getrandmax();
    }

    private function pick(array $a): mixed
    {
        return $a[(int) floor($this->r() * count($a))];
    }

    public function run(): void
    {
        mt_srand(2426);
        $hoy = now()->format('Y-m-d');

        // ---------- configuración ----------
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
                $F[$k]->versions()->create(['version' => $v['v'], 'notes' => $v['notes'], 'sections' => $v['sections'], 'published_at' => $v['date'], 'published_by' => 'Carmen Ortiz']);
            }
        }
        foreach ($E as $k => $e) {
            $e->update(['first_form_id' => $F[$formsEsp[$k][0]]->id, 'sub_form_id' => $F[$formsEsp[$k][1]]->id]);
        }

        // ---------- espacios ----------
        $R = [];
        foreach ([
            'r1' => ['Consultorio 1', 'Consultorio', 'Planta baja', 3, '07:00', '21:00', 0, 'Mesa de exploración, negatoscopio', 'Recepción', ''],
            'r2' => ['Consultorio 2', 'Consultorio', 'Planta baja', 3, '07:00', '21:00', 0, 'Mesa de exploración', 'Recepción', ''],
            'r3' => ['Consultorio 3', 'Consultorio', 'Primer piso', 3, '07:00', '21:00', 0, 'Mesa ginecológica, ultrasonido', 'Recepción', ''],
            'r4' => ['Consultorio 4', 'Consultorio', 'Primer piso', 3, '07:00', '21:00', 0, 'Martillo de reflejos, oftalmoscopio', 'Recepción', ''],
            'r5' => ['Consultorio 5', 'Consultorio', 'Primer piso', 3, '07:00', '21:00', 0, 'Electrocardiógrafo', 'Recepción', ''],
            'r6' => ['Consultorio 6', 'Consultorio', 'Primer piso', 4, '07:00', '21:00', 0, 'Báscula pediátrica', 'Recepción', ''],
            'r7' => ['Quirófano 1', 'Quirófano', 'Segundo piso', 8, '07:00', '21:00', 6500, 'Torre de artroscopia, arco en C', 'Jefatura de quirófanos', 'Limpieza de 30 min entre procedimientos'],
            'r8' => ['Quirófano 2', 'Quirófano', 'Segundo piso', 8, '07:00', '21:00', 6000, 'Torre de laparoscopia', 'Jefatura de quirófanos', 'Limpieza de 30 min entre procedimientos'],
            'r9' => ['Sala de rehabilitación', 'Sala de rehabilitación', 'Planta baja', 6, '07:00', '20:00', 0, 'Barras paralelas, electroterapia', 'Fisioterapia', ''],
            'r10' => ['Sala de procedimientos', 'Sala de procedimientos', 'Primer piso', 4, '08:00', '20:00', 1500, 'Material para infiltraciones y curaciones', 'Enfermería', ''],
            'r11' => ['Sala de juntas', 'Sala de juntas', 'Tercer piso', 12, '08:00', '19:00', 0, 'Pantalla, videoconferencia', 'Dirección', 'Reservar con 24 h de anticipación'],
            'r12' => ['Habitación 201', 'Habitación', 'Segundo piso', 1, '07:00', '21:00', 2200, 'Cama eléctrica, monitor', 'Enfermería', ''],
        ] as $k => [$n, $t, $l, $cap, $a, $b, $c, $eq, $resp, $reg]) {
            $R[$k] = Resource::create(['name' => $n, 'type' => $t, 'location' => $l, 'capacity' => $cap, 'opens_at' => $a, 'closes_at' => $b, 'cost' => $c, 'equipment' => $eq, 'responsible' => $resp, 'rules' => $reg ?: null]);
        }

        // ---------- médicos ----------
        $D = [];
        foreach ([
            'd1' => ['Dr. Juan Pérez Aguilar', 'e1', '777 310 2201', 'r1', 800, 30, 'monto', 300, [1, 2, 4], '08:00', '14:00', ''],
            'd2' => ['Dr. Marco Antonio González', 'e1', '777 310 2202', 'r2', 850, 40, 'monto', 300, [1, 2, 4, 5], '08:00', '13:20', 'Cirugía artroscópica los miércoles'],
            'd3' => ['Dra. Laura Méndez Ruiz', 'e2', '777 310 2203', 'r3', 900, 30, 'porcentaje', 50, [1, 2, 3, 4, 5], '09:00', '15:00', ''],
            'd4' => ['Dr. Ricardo Salazar Ortiz', 'e3', '777 310 2204', 'r4', 1100, 40, 'monto', 400, [1, 3, 5], '10:00', '16:00', ''],
            'd5' => ['Dra. Sofía Ríos Cárdenas', 'e4', '777 310 2205', 'r5', 1000, 30, 'monto', 0, [2, 4, 6], '08:00', '12:00', ''],
            'd6' => ['Dr. Andrés Campos Lara', 'e5', '777 310 2206', 'r6', 700, 20, 'monto', 0, [1, 2, 3, 4, 5, 6], '09:00', '14:00', ''],
            'd7' => ['Dra. Paula Herrera Vidal', 'e6', '777 310 2207', 'r1', 750, 30, 'monto', 200, [2, 3, 5], '15:00', '19:00', ''],
            'd8' => ['Lic. Diego Navarro Paz', 'e7', '777 310 2208', 'r9', 500, 60, 'monto', 0, [1, 2, 3, 4, 5], '07:00', '13:00', ''],
            'd9' => ['Psic. Elena Vargas Soto', 'e8', '777 310 2209', 'r2', 650, 50, 'monto', 200, [1, 3, 4], '16:00', '20:10', ''],
        ] as $k => [$n, $e, $tel, $r, $c, $dur, $dm, $dv, $dias, $a, $b, $obs]) {
            $D[$k] = Doctor::create(['name' => $n, 'specialty_id' => $E[$e]->id, 'phone' => $tel, 'resource_id' => $R[$r]->id, 'cost' => $c, 'duration' => $dur,
                'deposit_mode' => $dm, 'deposit_value' => $dv, 'days' => $dias, 'start_time' => $a, 'end_time' => $b, 'notes' => $obs ?: null]);
        }

        // ---------- usuarios ----------
        $pw = 'demo1234';
        User::create(['name' => 'Carmen Ortiz', 'email' => 'cortiz@clinicasanantonio.mx', 'role' => 'Administrador', 'password' => $pw]);
        User::create(['name' => 'Mónica Fuentes', 'email' => 'mfuentes@clinicasanantonio.mx', 'role' => 'Coordinador', 'password' => $pw]);
        User::create(['name' => 'Ana Torres', 'email' => 'atorres@clinicasanantonio.mx', 'role' => 'Recepción', 'password' => $pw]);
        User::create(['name' => 'Luis Ramírez', 'email' => 'lramirez@clinicasanantonio.mx', 'role' => 'Recepción', 'password' => $pw]);
        $i = 1;
        foreach ($D as $d) {
            User::create(['name' => $d->name, 'email' => 'medico'.$i++.'@clinicasanantonio.mx', 'role' => 'Médico', 'doctor_id' => $d->id, 'password' => $pw]);
        }
        User::create(['name' => 'Contraloría interna', 'email' => 'contraloria@clinicasanantonio.mx', 'role' => 'Consulta', 'password' => $pw]);

        // ---------- pacientes ----------
        $nombres = ['Juan Pérez Ortega', 'María López Hernández', 'Guadalupe Sánchez Ruiz', 'José Luis Martínez Cruz', 'Ana Karen Flores Díaz', 'Roberto Jiménez Vega', 'Fernanda Castillo Mora', 'Miguel Ángel Reyes Soto', 'Patricia Gómez Navarro', 'Carlos Eduardo Ramos Gil', 'Daniela Torres Medina', 'Alejandro Morales Peña', 'Verónica Herrera Luna', 'Jorge Alberto Mendoza', 'Sofía Aguilar Campos', 'Ricardo Domínguez Ríos', 'Lucía Vázquez Ortiz', 'Fernando Ruiz Salgado', 'Mariana Delgado Fuentes', 'Héctor Guzmán Rosas', 'Claudia Romero Ibarra', 'Eduardo Silva Cortés', 'Andrea Cabrera Montes', 'Óscar Núñez Valdez', 'Gabriela Ponce Esquivel', 'Raúl Contreras Lagos', 'Itzel Salazar Bravo', 'Arturo Vega Carrillo', 'Paola Estrada Rivas', 'Emiliano Rojas Fierro', 'Ximena Lara Quiroz', 'Sergio Paredes Ochoa', 'Renata Mejía Solís', 'Tomás Arellano Pineda', 'Valeria Cervantes Ávila', 'Luis Fernando Robles', 'Natalia Bautista Leal', 'Iván Zamora Tapia', 'Camila Ochoa Maldonado', 'Mateo Villalobos Sáenz'];
        $P = [];
        foreach ($nombres as $i => $n) {
            $y = $i === 0 ? 1981 : ($i % 7 === 3 ? 2014 + ($i % 9) : 1950 + (int) floor($this->r() * 55));
            $m = $i === 0 ? 3 : 1 + (int) floor($this->r() * 12);
            $d = $i === 0 ? 14 : 1 + (int) floor($this->r() * 27);
            $partes = explode(' ', Str::ascii(mb_strtolower($n)));
            $P[] = Patient::create(['sigma_id' => 'SG-'.(10230 + $i * 7), 'name' => $n, 'birth_date' => sprintf('%04d-%02d-%02d', $y, $m, $d),
                'phone' => '777 '.(100 + (int) floor($this->r() * 899)).' '.(1000 + (int) floor($this->r() * 8999)),
                'email' => $partes[0].'.'.($partes[1] ?? 'x').'@correo.mx']);
        }

        // ---------- bloqueos ----------
        $bk = fn (array $o) => Block::create($o + ['scope' => 'doctor', 'recurring' => false, 'days' => [], 'created_by' => 'Mónica Fuentes']);
        $siguienteDia = function (Doctor $d, string $desde) {
            $x = $desde;
            for ($i = 0; $i < 14; $i++) {
                if ($d->worksOn($x)) {
                    return $x;
                }
                $x = Agenda::sumarDias($x, 1);
            }

            return $desde;
        };
        $bk(['target_id' => $D['d3']->id, 'type' => 'Comida', 'date' => Agenda::sumarDias($hoy, -30), 'start_time' => '13:00', 'end_time' => '14:00', 'recurring' => true, 'days' => [1, 2, 3, 4, 5], 'notes' => 'Horario de comida fijo']);
        $bk(['target_id' => $D['d4']->id, 'type' => 'Comida', 'date' => Agenda::sumarDias($hoy, -30), 'start_time' => '13:20', 'end_time' => '14:00', 'recurring' => true, 'days' => [1, 3, 5]]);
        $bk(['target_id' => $D['d1']->id, 'type' => 'Reunión', 'date' => $siguienteDia($D['d1'], $hoy), 'start_time' => '11:00', 'end_time' => '12:00', 'notes' => 'Sesión académica del servicio']);
        $bk(['target_id' => $D['d5']->id, 'type' => 'Fin de agenda', 'date' => $siguienteDia($D['d5'], Agenda::sumarDias($hoy, 1)), 'start_time' => '11:00', 'end_time' => '12:00', 'notes' => 'La doctora sale a congreso']);
        $bk(['target_id' => $D['d7']->id, 'type' => 'Vacaciones', 'date' => $siguienteDia($D['d7'], Agenda::sumarDias($hoy, 8)), 'start_time' => '00:00', 'end_time' => '23:59', 'notes' => 'Vacaciones programadas']);
        $bk(['scope' => 'resource', 'target_id' => $R['r8']->id, 'type' => 'Mantenimiento', 'date' => $hoy, 'start_time' => '15:00', 'end_time' => '18:00', 'notes' => 'Mantenimiento preventivo de torre de laparoscopia']);
        $bloqueos = Block::all();

        // ---------- citas ----------
        $motivos = ['e1' => ['Dolor de rodilla', 'Dolor lumbar', 'Esguince de tobillo', 'Dolor de hombro', 'Fractura de muñeca en seguimiento'], 'e2' => ['Revisión anual', 'Dolor pélvico', 'Control prenatal', 'Irregularidad menstrual'], 'e3' => ['Cefalea recurrente', 'Mareos', 'Hormigueo en manos', 'Pérdida de memoria'], 'e4' => ['Hipertensión', 'Palpitaciones', 'Chequeo preoperatorio', 'Dolor torácico'], 'e5' => ['Control del niño sano', 'Fiebre', 'Tos persistente', 'Vacunación'], 'e6' => ['Acné', 'Lunar a revisar', 'Dermatitis', 'Caída de cabello'], 'e7' => ['Rehabilitación de rodilla', 'Lumbalgia', 'Terapia posquirúrgica'], 'e8' => ['Ansiedad', 'Terapia individual', 'Seguimiento']];
        $archivosDemo = ['Radiografía AP y lateral.jpg', 'Resonancia magnética.pdf', 'Biometría hemática.pdf', 'Química sanguínea.pdf', 'Ultrasonido.pdf', 'Receta anterior.jpg', 'Identificación oficial.jpg', 'Electrocardiograma.pdf'];
        $espKey = array_flip(array_map(fn ($e) => $e->id, $E));
        $ahora = now()->hour * 60 + now()->minute;
        $recepcion = ['Ana Torres', 'Luis Ramírez'];

        for ($off = -10; $off <= 24; $off++) {
            $fecha = Agenda::sumarDias($hoy, $off);
            foreach ($D as $dk => $d) {
                if (! $d->worksOn($fecha)) {
                    continue;
                }
                $ek = $espKey[$d->specialty_id];
                $usados = [];
                $fin = Agenda::min($d->end_time);
                for ($m = Agenda::min($d->start_time); $m + $d->duration <= $fin; $m += $d->duration) {
                    $bloq = $bloqueos->first(fn ($b) => $b->scope === 'doctor' && $b->target_id === $d->id && $b->appliesOn($fecha)
                        && Agenda::traslapa($m, $m + $d->duration, Agenda::min($b->start_time), Agenda::min($b->end_time)));
                    if ($bloq) {
                        continue;
                    }
                    $prob = $off < 0 ? 0.62 : ($off === 0 ? 0.7 : ($off <= 7 ? 0.55 - $off * 0.03 : 0.22 - $off * 0.006));
                    if ($this->r() > $prob) {
                        continue;
                    }
                    do {
                        $pt = $this->pick($P);
                    } while (isset($usados[$pt->id]));
                    $usados[$pt->id] = true;
                    $sub = $this->r() < 0.45;
                    $tipo = $ek === 'e7' ? 'Terapia' : ($sub ? 'Consulta subsecuente' : 'Primera consulta');
                    $dep = $d->depositAmount();
                    $pagado = $dep;
                    $pasado = $off < 0 || ($off === 0 && $m + $d->duration <= $ahora);
                    $ahoraEn = $off === 0 && $m <= $ahora && $ahora < $m + $d->duration;
                    if ($pasado) {
                        $estado = $this->r() < 0.08 ? 'Cancelada' : 'Atendida';
                        $hc = $estado === 'Atendida' ? 'Revisado' : 'Pendiente';
                    } elseif ($ahoraEn) {
                        $estado = 'En consulta';
                        $hc = 'Revisado';
                    } else {
                        $x = $this->r();
                        if ($dep > 0 && $x < 0.28) {
                            $pagado = $x < 0.1 ? round($dep / 2) : 0;
                            $estado = 'Anticipo pendiente';
                        } else {
                            $estado = $this->r() < ($off <= 2 ? 0.6 : 0.35) ? 'Confirmada' : 'Agendada';
                        }
                        $h = $this->r();
                        $hc = $off > 9 ? ($h < 0.75 ? 'Pendiente' : 'En proceso') : ($h < 0.42 ? 'Completado' : ($h < 0.62 ? 'En proceso' : ($h < 0.95 ? 'Pendiente' : 'Requiere actualización')));
                    }
                    $form = Formatos::asignar($d->specialty_id, $tipo);
                    $form->load('versions');
                    $ver = ($form->id === $F['f1']->id && $off < -4) ? '1.0' : $form->versions->last()->version;
                    $motivo = $this->pick($motivos[$ek]);
                    $creada = Agenda::sumarDias($fecha, -6).' 09:00:00';
                    $a = Appointment::create([
                        'token' => Str::random(32), 'patient_id' => $pt->id, 'doctor_id' => $d->id, 'specialty_id' => $d->specialty_id,
                        'date' => $fecha, 'time' => Agenda::hhmm($m), 'duration' => $d->duration, 'type' => $tipo, 'reason' => $motivo,
                        'cost' => $d->cost, 'deposit' => $dep, 'paid' => $pagado, 'payment_method' => $pagado > 0 ? $this->pick(Catalogo::FORMAS_PAGO) : null,
                        'channel' => 'WhatsApp', 'confirmed' => $estado === 'Confirmada', 'status' => $estado,
                        'clinical_form_id' => $form->id, 'form_version' => $ver, 'hc_status' => $hc, 'hc_data' => [], 'created_by' => $this->pick($recepcion),
                    ]);
                    $a->created_at = $creada;
                    $a->save();
                    if ($pagado > 0) {
                        $a->payments()->create(['amount' => $pagado, 'method' => $a->payment_method, 'user_name' => $this->pick($recepcion), 'paid_at' => Agenda::sumarDias($fecha, -3).' 10:00:00']);
                    }
                    $a->events()->create(['text' => 'Cita registrada', 'user_name' => $a->created_by, 'created_at' => $creada]);
                    if ($hc !== 'Pendiente') {
                        $datos = $this->respuestas($form->versions->firstWhere('version', $ver)->sections, $pt, $a, $hc === 'En proceso');
                        $upd = ['hc_data' => $datos];
                        if ($hc !== 'En proceso') {
                            $upd['hc_submitted_at'] = Agenda::sumarDias($fecha, -1).' 15:12:00';
                            $nf = (int) floor($this->r() * 3);
                            for ($k = 0; $k < $nf; $k++) {
                                $a->files()->create(['patient_id' => $pt->id, 'field_id' => 'archivos', 'original_name' => $this->pick($archivosDemo), 'size_kb' => (int) round(200 + $this->r() * 3000)]);
                            }
                        }
                        if ($hc === 'Revisado') {
                            $upd['reviewed_by'] = $d->name;
                        }
                        $a->update($upd);
                    }
                    if ($estado === 'Cancelada') {
                        $a->events()->create(['text' => 'Cita cancelada: Solicitud del paciente', 'user_name' => 'Ana Torres', 'created_at' => Agenda::sumarDias($fecha, -1).' 08:00:00']);
                    }
                    if ($estado === 'Atendida') {
                        $a->events()->create(['text' => 'Atención concluida', 'user_name' => $d->name, 'created_at' => $fecha.' '.Agenda::hhmm($m + $d->duration).':00']);
                    }
                }
            }
        }

        // ---------- casos del documento: Juan Pérez (rodilla) y María López (anticipo pendiente) ----------
        $futuras = Appointment::where('date', '>=', $hoy)->whereIn('status', ['Agendada', 'Confirmada', 'Anticipo pendiente'])->orderBy('date')->orderBy('time')->get();
        $j = $futuras->firstWhere('doctor_id', $D['d2']->id) ?? $futuras->firstWhere('specialty_id', $E['e1']->id);
        if ($j) {
            $j->files()->delete();
            $j->payments()->delete();
            $sec = $F['f1']->versions()->where('version', '1.1')->first()->sections;
            $datos = $this->respuestas($sec, $P[0], $j, false);
            $datos = array_merge($datos, [
                'motivo' => 'Dolor de rodilla derecha al subir escaleras',
                'padec' => 'Dolor en rodilla derecha de 5 semanas de evolución, inició después de jugar fútbol. Aumenta al subir escaleras y en las noches.',
                'zona' => 'Rodilla', 'evol' => '1 a 4 semanas', 'dolor' => 7, 'lesiones' => 'Sí', 'trat' => 'Naproxeno 250 mg por 10 días, compresas frías',
                'meds' => ['none' => false, 'rows' => [['m' => 'Losartán', 'd' => '50 mg', 'f' => 'Cada 24 h'], ['m' => 'Naproxeno', 'd' => '250 mg', 'f' => 'Cada 12 h']]],
                'alerg' => ['none' => false, 'rows' => [['t' => 'Medicamento', 'd' => 'Penicilina (urticaria)']]],
            ]);
            $j->update(['patient_id' => $P[0]->id, 'type' => 'Primera consulta', 'reason' => 'Dolor de rodilla', 'status' => 'Confirmada', 'confirmed' => true,
                'paid' => $j->deposit, 'clinical_form_id' => $F['f1']->id, 'form_version' => '1.1', 'hc_status' => 'Completado',
                'hc_submitted_at' => Agenda::sumarDias($j->dateString(), -1).' 14:41:00', 'hc_data' => $datos, 'reviewed_by' => null]);
            if ($j->deposit > 0) {
                $j->payments()->create(['amount' => $j->deposit, 'method' => 'Transferencia', 'user_name' => 'Ana Torres', 'paid_at' => Agenda::sumarDias($j->dateString(), -2).' 11:00:00']);
            }
            foreach ([['Radiografía rodilla derecha AP.jpg', 1840], ['Radiografía rodilla derecha lateral.jpg', 1720], ['Resonancia magnética rodilla.pdf', 4210]] as [$n, $s]) {
                $j->files()->create(['patient_id' => $P[0]->id, 'field_id' => 'archivos', 'original_name' => $n, 'size_kb' => $s]);
            }
        }
        $ml = $futuras->first(fn ($a) => $a->specialty_id === $E['e2']->id && $a->id !== $j?->id);
        if ($ml) {
            $ml->files()->delete();
            $ml->payments()->delete();
            $ml->update(['patient_id' => $P[1]->id, 'status' => 'Anticipo pendiente', 'paid' => 0, 'hc_status' => 'Pendiente', 'hc_data' => [],
                'reason' => 'Revisión anual', 'type' => 'Primera consulta', 'clinical_form_id' => $F['f3']->id, 'form_version' => '1.0', 'confirmed' => false]);
        }

        // ---------- reservaciones de espacios ----------
        $res = fn (array $o) => Reservation::create($o + ['status' => 'Programada', 'created_by' => 'Mónica Fuentes']);
        for ($off = -3; $off <= 10; $off++) {
            $fecha = Agenda::sumarDias($hoy, $off);
            if (Agenda::diaSemana($fecha) === 0) {
                continue;
            }
            if ($off === 0) {
                $res(['resource_id' => $R['r7']->id, 'date' => $fecha, 'start_time' => '08:00', 'duration' => 120, 'procedure' => 'Artroscopia de rodilla', 'doctor_id' => $D['d2']->id, 'patient_id' => $P[5]->id]);
                $res(['resource_id' => $R['r7']->id, 'date' => $fecha, 'start_time' => '11:00', 'duration' => 150, 'procedure' => 'Reconstrucción de ligamento cruzado anterior', 'doctor_id' => $D['d1']->id, 'patient_id' => $P[11]->id]);
                $res(['resource_id' => $R['r8']->id, 'date' => $fecha, 'start_time' => '09:00', 'duration' => 90, 'procedure' => 'Histeroscopia diagnóstica', 'doctor_id' => $D['d3']->id, 'patient_id' => $P[6]->id]);
                $res(['resource_id' => $R['r10']->id, 'date' => $fecha, 'start_time' => '10:00', 'duration' => 30, 'procedure' => 'Infiltración de hombro', 'doctor_id' => $D['d1']->id, 'patient_id' => $P[9]->id]);
                $res(['resource_id' => $R['r11']->id, 'date' => $fecha, 'start_time' => '13:00', 'duration' => 60, 'procedure' => 'Sesión clínica mensual']);
                $res(['resource_id' => $R['r12']->id, 'date' => $fecha, 'start_time' => '10:30', 'duration' => 480, 'procedure' => 'Recuperación posquirúrgica', 'doctor_id' => $D['d2']->id, 'patient_id' => $P[5]->id]);

                continue;
            }
            if ($this->r() < 0.7) {
                $res(['resource_id' => $R['r7']->id, 'date' => $fecha, 'start_time' => $this->pick(['08:00', '09:00', '10:00']), 'duration' => $this->pick([90, 120]), 'procedure' => $this->pick(['Artroscopia de hombro', 'Osteosíntesis de radio', 'Artroscopia de rodilla']), 'doctor_id' => $this->pick([$D['d1']->id, $D['d2']->id]), 'patient_id' => $this->pick($P)->id]);
            }
            if ($this->r() < 0.5) {
                $res(['resource_id' => $R['r8']->id, 'date' => $fecha, 'start_time' => $this->pick(['12:00', '13:00']), 'duration' => $this->pick([60, 90]), 'procedure' => $this->pick(['Laparoscopia diagnóstica', 'Histeroscopia', 'Colocación de DIU']), 'doctor_id' => $D['d3']->id, 'patient_id' => $this->pick($P)->id]);
            }
            if ($this->r() < 0.5) {
                $res(['resource_id' => $R['r10']->id, 'date' => $fecha, 'start_time' => $this->pick(['15:00', '16:00', '17:00']), 'duration' => 30, 'procedure' => $this->pick(['Retiro de lunar', 'Curación', 'Infiltración']), 'doctor_id' => $this->pick([$D['d7']->id, $D['d1']->id]), 'patient_id' => $this->pick($P)->id]);
            }
        }

        // ---------- lista de espera ----------
        $w = fn (array $o) => WaitlistEntry::create($o + ['status' => 'En espera']);
        $w(['patient_id' => $P[12]->id, 'specialty_id' => $E['e1']->id, 'doctor_id' => $D['d2']->id, 'requested_at' => Agenda::sumarDias($hoy, -4), 'preference' => 'Mañana', 'priority' => 'Alta', 'notes' => 'Dolor intenso, desea la primera cita que se libere']);
        $w(['patient_id' => $P[17]->id, 'specialty_id' => $E['e3']->id, 'requested_at' => Agenda::sumarDias($hoy, -3), 'preference' => 'Cualquier horario', 'priority' => 'Media']);
        $w(['patient_id' => $P[22]->id, 'specialty_id' => $E['e2']->id, 'doctor_id' => $D['d3']->id, 'requested_at' => Agenda::sumarDias($hoy, -2), 'preference' => 'Mañana', 'priority' => 'Media', 'notes' => 'Solo puede antes de las 12:00']);
        $w(['patient_id' => $P[27]->id, 'specialty_id' => $E['e1']->id, 'requested_at' => Agenda::sumarDias($hoy, -1), 'preference' => 'Cualquier horario', 'priority' => 'Baja']);
        $w(['patient_id' => $P[31]->id, 'specialty_id' => $E['e8']->id, 'doctor_id' => $D['d9']->id, 'requested_at' => Agenda::sumarDias($hoy, -6), 'preference' => 'Tarde', 'priority' => 'Alta', 'notes' => 'Seguimiento quincenal']);

        // ---------- notificaciones previas ----------
        $i = 0;
        Appointment::query()->active()->whereBetween('date', [$hoy, Agenda::sumarDias($hoy, 3)])->orderBy('date')->orderBy('time')->limit(14)->get()
            ->each(function (Appointment $a) use (&$i) {
                $a->messages()->create(['channel' => $i++ % 5 === 0 ? 'SMS' : 'WhatsApp', 'kind' => 'registro', 'body' => Mensajeria::texto($a, 'registro'),
                    'status' => 'Leído', 'sent_at' => $a->created_at, 'read_at' => $a->created_at]);
            });

        AuditLog::create(['user_name' => 'Carmen Ortiz', 'action' => 'Formato publicado', 'detail' => 'Historia clínica de Traumatología v1.1', 'created_at' => Agenda::sumarDias($hoy, -1).' 09:02:00']);
        AuditLog::create(['user_name' => 'Mónica Fuentes', 'action' => 'Bloqueo creado', 'detail' => 'Dr. Juan Pérez Aguilar: Reunión 11:00 a 12:00', 'created_at' => Agenda::sumarDias($hoy, -1).' 10:30:00']);

        $this->call(AdministradorSeeder::class);
    }

    /** Respuestas simuladas para un formato (port de genAnswers del prototipo). */
    private function respuestas(array $secciones, Patient $pt, Appointment $a, bool $parcial): array
    {
        $out = [];
        $n = 0;
        $txt = ['Sin cambios relevantes', 'Ninguno', 'No refiere', 'Paracetamol ocasional', 'Reposo y antiinflamatorios'];
        foreach ($secciones as $s) {
            foreach ($s['fields'] as $f) {
                if ($f['mode'] === 'readonly') {
                    continue;
                }
                $n++;
                if ($parcial && $n > 6) {
                    continue;
                }
                $v = match ($f['type']) {
                    'text' => ($f['prefill'] ?? '') === 'phone' ? $pt->phone : ($f['id'] === 'motivo' ? $a->reason : $this->pick($txt)),
                    'textarea' => $f['id'] === 'motivo' ? $a->reason : ($f['id'] === 'padec' ? "{$a->reason} desde hace ".(2 + (int) floor($this->r() * 6)).' semanas, con aumento progresivo de las molestias.' : $this->pick($txt)),
                    'number' => $f['id'] === 'menarca' ? 11 + (int) floor($this->r() * 4) : ($f['id'] === 'durac' ? 26 + (int) floor($this->r() * 6) : (int) floor($this->r() * 3)),
                    'date' => Agenda::sumarDias($a->dateString(), -(8 + (int) floor($this->r() * 40))),
                    'time' => '08:00',
                    'yesno' => $this->pick(['Sí', 'No']),
                    'single', 'select' => $this->pick($f['options']),
                    'multi' => [$this->pick($f['options'])],
                    'history' => ['sel' => $this->r() < 0.4 ? ['Ninguno'] : [$this->pick(array_slice($f['options'], 0, -1))], 'notes' => ''],
                    'scale' => 2 + (int) floor($this->r() * 7),
                    'allergies' => $this->r() < 0.6 ? ['none' => true, 'rows' => []] : ['none' => false, 'rows' => [['t' => $this->pick(['Medicamento', 'Alimento', 'Otro']), 'd' => $this->pick(['Penicilina', 'Sulfas', 'Mariscos', 'Látex', 'Ácido acetilsalicílico'])]]],
                    'meds' => $this->r() < 0.45 ? ['none' => true, 'rows' => []] : ['none' => false, 'rows' => [['m' => $this->pick(['Metformina', 'Losartán', 'Levotiroxina', 'Omeprazol', 'Paracetamol']), 'd' => $this->pick(['500 mg', '50 mg', '100 mcg', '20 mg']), 'f' => $this->pick(['Cada 24 h', 'Cada 12 h', 'Cada 8 h'])]]],
                    'consent' => ['accepted' => true, 'at' => Agenda::sumarDias($a->dateString(), -1).'T20:30:00', 'v' => $a->form_version],
                    default => null,
                };
                if ($v !== null) {
                    $out[$f['id']] = $v;
                }
            }
        }

        return $out;
    }
}
