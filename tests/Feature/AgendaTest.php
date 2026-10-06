<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use App\Services\Agenda;
use App\Services\Formatos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Reglas de negocio principales de la propuesta (sección 44) y permisos por rol. */
class AgendaTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function usuario(string $rol): User
    {
        return User::where('role', $rol)->firstOrFail();
    }

    /** Primer horario libre de un médico a partir de mañana. */
    private function libre(Doctor $d): array
    {
        $r = (new Agenda)->proximos(null, $d->id, Agenda::sumarDias(now()->format('Y-m-d'), 1), 30, '', 1)[0];

        return ['date' => $r['date'], 'time' => $r['times'][0]];
    }

    private function datosCita(Doctor $d, array $slot): array
    {
        return ['patient_id' => Patient::first()->id, 'doctor_id' => $d->id, 'date' => $slot['date'], 'time' => $slot['time'],
            'type' => 'Primera consulta', 'cost' => $d->cost, 'deposit' => $d->depositAmount(), 'paid' => 0, 'channel' => 'WhatsApp'];
    }

    public function test_la_agenda_se_genera_a_partir_de_las_reglas_del_medico(): void
    {
        $d = Doctor::where('name', 'like', '%Juan Pérez%')->first(); // martes y jueves... 08:00-14:00 cada 30 min
        $fecha = now()->next('Tuesday')->format('Y-m-d');
        $espacios = collect((new Agenda($fecha))->espacios($d, $fecha))->where('off', false);
        $this->assertCount(12, $espacios);
        $this->assertSame('08:00', $espacios->first()['time']);
        $this->assertSame('13:30', $espacios->last()['time']);
        $this->assertSame([], (new Agenda)->espacios($d, now()->next('Sunday')->format('Y-m-d')));
    }

    public function test_no_se_puede_agendar_sobre_un_horario_ocupado(): void
    {
        $d = Doctor::first();
        $datos = $this->datosCita($d, $this->libre($d));
        $this->actingAs($this->usuario('Recepción'))->postJson('/api/citas', $datos)->assertOk();
        $this->postJson('/api/citas', $datos)->assertStatus(422)->assertJsonFragment(['message' => 'Ese horario ya no está disponible (anticipo pendiente). Elige otro.']);
    }

    public function test_cita_con_anticipo_queda_pendiente_y_se_liquida(): void
    {
        $d = Doctor::where('deposit_value', '>', 0)->where('deposit_mode', 'monto')->first();
        $this->actingAs($this->usuario('Recepción'))->postJson('/api/citas', $this->datosCita($d, $this->libre($d)))->assertOk();
        $a = Appointment::latest('id')->first();
        $this->assertSame('Anticipo pendiente', $a->status);
        $this->assertNotNull($a->clinical_form_id, 'Se asigna un formato clínico al registrar');
        $this->assertSame(1, $a->messages()->count(), 'Se notifica al paciente');
        $this->postJson("/api/citas/{$a->id}/pago", ['amount' => $a->deposit + 1, 'method' => 'Efectivo'])->assertStatus(422);
        $this->postJson("/api/citas/{$a->id}/pago", ['amount' => $a->deposit, 'method' => 'Efectivo'])->assertOk();
        $this->assertSame('Agendada', $a->fresh()->status);
        $this->assertSame('Pagado', $a->fresh()->depositState());
    }

    public function test_los_bloqueos_impiden_citas_y_no_se_crean_sobre_citas_existentes(): void
    {
        $d = Doctor::first();
        $slot = $this->libre($d);
        $admin = $this->usuario('Coordinador');
        $this->actingAs($admin)->postJson('/api/bloqueos', ['scope' => 'doctor', 'target_id' => $d->id, 'type' => 'Reunión', 'date' => $slot['date'],
            'start_time' => $slot['time'], 'end_time' => Agenda::hhmm(Agenda::min($slot['time']) + $d->duration)])->assertOk();
        $this->postJson('/api/citas', $this->datosCita($d, $slot))->assertStatus(422);

        $otro = $this->libre($d);
        $this->postJson('/api/citas', $this->datosCita($d, $otro))->assertOk();
        $this->postJson('/api/bloqueos', ['scope' => 'doctor', 'target_id' => $d->id, 'type' => 'Comida', 'date' => $otro['date'],
            'all_day' => true])->assertStatus(422)->assertJsonStructure(['conflictos']);
    }

    public function test_cancelar_libera_el_horario_y_reagendar_conserva_el_historial(): void
    {
        $d = Doctor::first();
        $slot = $this->libre($d);
        $this->actingAs($this->usuario('Recepción'))->postJson('/api/citas', $this->datosCita($d, $slot))->assertOk();
        $a = Appointment::latest('id')->first();
        $nuevo = (new Agenda)->disponibles($d, $slot['date'])[0];
        $r = $this->postJson("/api/citas/{$a->id}/reagendar", ['doctor_id' => $d->id, 'date' => $slot['date'], 'time' => $nuevo, 'reason' => 'Prueba'])->assertOk();
        $this->assertSame('Reagendada', $a->fresh()->status);
        $n = Appointment::find($r->json('id'));
        $this->assertTrue($n->events()->where('text', 'Cita registrada')->exists());
        $this->assertContains($slot['time'], (new Agenda)->disponibles($d, $slot['date']), 'El horario original quedó libre');

        $this->postJson("/api/citas/{$n->id}/cancelar", ['reason' => 'Solicitud del paciente', 'notify' => false])->assertOk();
        $this->assertContains($nuevo, (new Agenda)->disponibles($d, $slot['date']));
    }

    public function test_recepcion_no_ve_contenido_clinico_y_medico_solo_sus_pacientes(): void
    {
        $a = Appointment::where('hc_status', 'Completado')->first();
        $this->actingAs($this->usuario('Recepción'))->getJson("/api/citas/{$a->id}/historia")->assertForbidden();

        $propio = User::where('doctor_id', $a->doctor_id)->first();
        $this->actingAs($propio)->getJson("/api/citas/{$a->id}/historia")->assertOk()->assertJsonStructure(['resumen', 'secciones']);
        $ajeno = User::where('role', 'Médico')->where('doctor_id', '!=', $a->doctor_id)->first();
        $this->actingAs($ajeno)->getJson("/api/citas/{$a->id}/historia")->assertForbidden();
        $this->actingAs($this->usuario('Consulta'))->postJson('/api/citas', [])->assertForbidden();
    }

    public function test_el_paciente_completa_su_historia_desde_el_enlace(): void
    {
        $a = Appointment::where('hc_status', 'Pendiente')->where('date', '>=', now()->format('Y-m-d'))->active()->first();
        $this->get("/p/{$a->token}")->assertOk();
        $this->postJson("/p/{$a->token}/enviar", ['respuestas' => []])->assertStatus(422)->assertJsonStructure(['faltan']);

        // Se responden todos los obligatorios con valores válidos según su tipo.
        $resp = [];
        foreach (Formatos::secciones($a) as $s) {
            foreach ($s['fields'] as $f) {
                if ($f['mode'] !== 'required') {
                    continue;
                }
                $resp[$f['id']] = match ($f['type']) {
                    'number', 'scale' => 3, 'date' => '2026-01-01', 'yesno' => 'Sí', 'single', 'select' => $f['options'][0],
                    'multi' => [$f['options'][0]], 'allergies', 'meds' => ['none' => true, 'rows' => []], 'consent' => ['accepted' => true],
                    default => 'Respuesta',
                };
            }
        }
        $tieneArchivoObligatorio = collect(Formatos::secciones($a))->flatMap(fn ($s) => $s['fields'])->contains(fn ($f) => $f['mode'] === 'required' && $f['type'] === 'files');
        if ($tieneArchivoObligatorio) {
            $this->markTestSkipped('Formato con archivo obligatorio');
        }
        $this->postJson("/p/{$a->token}/enviar", ['respuestas' => $resp])->assertOk();
        $this->assertSame('Completado', $a->fresh()->hc_status);
        $this->postJson("/p/{$a->token}/guardar", ['respuestas' => $resp])->assertStatus(422);
    }
}
