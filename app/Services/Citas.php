<?php

namespace App\Services;

use App\Exceptions\ReglaNegocio;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Support\Catalogo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Reglas de negocio de las citas: registro, confirmación, anticipos, cancelación y reagendamiento. */
class Citas
{
    private static function usuario(): string
    {
        return auth()->user()?->name ?? 'Sistema';
    }

    /**
     * Registra una cita validando que el horario exista en la agenda del médico y esté disponible.
     * Se bloquea la fila del médico para evitar dobles reservaciones concurrentes.
     */
    public static function crear(array $o, bool $notificar = true, ?Appointment $origen = null): Appointment
    {
        return DB::transaction(function () use ($o, $notificar, $origen) {
            $d = Doctor::lockForUpdate()->find($o['doctor_id']) ?? throw new ReglaNegocio('Selecciona un médico.');
            $p = Patient::find($o['patient_id']) ?? throw new ReglaNegocio('Selecciona un paciente existente o da de alta uno nuevo.');
            $agenda = new Agenda($o['date']);
            $esp = collect($agenda->espacios($d, $o['date']))->first(fn ($s) => $s['time'] === $o['time'] && ! $s['off']);
            if (! $esp) {
                throw new ReglaNegocio('Ese horario está fuera de la agenda configurada del médico.');
            }
            if ($esp['state'] !== 'Disponible') {
                throw new ReglaNegocio('Ese horario ya no está disponible ('.mb_strtolower($esp['state']).'). Elige otro.');
            }
            $deposito = (float) ($o['deposit'] ?? 0);
            $pagado = (float) ($o['paid'] ?? 0);
            if ($deposito > 0 && $pagado > $deposito) {
                throw new ReglaNegocio('El anticipo recibido no puede ser mayor al requerido.');
            }
            $tipo = $o['type'] ?? 'Primera consulta';
            $form = $origen?->form ?? Formatos::asignar($d->specialty_id, $tipo);

            $a = Appointment::create([
                'token' => Str::random(32),
                'patient_id' => $p->id, 'doctor_id' => $d->id, 'specialty_id' => $d->specialty_id,
                'date' => $o['date'], 'time' => $o['time'], 'duration' => $d->duration, 'type' => $tipo,
                'reason' => $o['reason'] ?? null, 'cost' => (float) ($o['cost'] ?? $d->cost), 'deposit' => $deposito, 'paid' => $pagado,
                'payment_method' => $o['payment_method'] ?? null, 'notes' => $o['notes'] ?? null,
                'channel' => $o['channel'] ?? 'WhatsApp', 'confirmed' => false,
                'status' => $deposito > 0 && $pagado < $deposito ? 'Anticipo pendiente' : 'Agendada',
                'clinical_form_id' => $form->id, 'form_version' => $origen?->form_version ?? $form->currentVersion->version,
                'hc_status' => 'Pendiente', 'hc_data' => [], 'created_by' => self::usuario(),
                'rescheduled_from_id' => $origen?->id,
            ]);
            if ($pagado > 0) {
                $a->payments()->create(['amount' => $pagado, 'method' => $o['payment_method'] ?? 'Efectivo', 'user_name' => self::usuario(), 'paid_at' => now()]);
            }
            if (! $origen) {
                $a->registrar('Cita registrada');
                Auditoria::log('Cita registrada', "{$p->name} con {$d->name}, ".Catalogo::fechaCorta($a->dateString())." {$a->time}", $a);
                Auditoria::log('Historia clínica asignada', "{$form->name} v{$a->form_version} para {$p->name}", $a);
            }
            if ($notificar) {
                Mensajeria::enviar($a, 'registro', $a->channel);
            }

            return $a;
        });
    }

    public static function confirmar(Appointment $a, bool $porPaciente = false): void
    {
        $a->confirmed = true;
        if ($a->status === 'Agendada') {
            $a->status = 'Confirmada';
        }
        $a->save();
        if ($porPaciente) {
            $a->registrar('El paciente confirmó su asistencia desde el enlace', $a->patient->name);
            Auditoria::log('Confirmación del paciente', $a->patient->name, $a, usuario: $a->patient->name);
            $a->messages()->create(['channel' => 'Sistema', 'kind' => 'confirmacion', 'body' => Mensajeria::texto($a, 'confirmacion'), 'status' => 'Leído', 'sent_at' => now(), 'read_at' => now()]);
        } else {
            $a->registrar('Asistencia confirmada'.($a->status === 'Anticipo pendiente' ? ' (anticipo aún pendiente)' : ''));
            Auditoria::log('Cita confirmada', $a->patient->name, $a);
        }
    }

    public static function cambiarEstado(Appointment $a, string $estado): void
    {
        if (! in_array($estado, Catalogo::ESTADOS_MANUALES, true)) {
            throw new ReglaNegocio('Estado no permitido.');
        }
        if (! $a->isActive()) {
            throw new ReglaNegocio('La cita ya no está activa.');
        }
        if ($estado === 'Anticipo pendiente' && ! ($a->deposit > $a->paid)) {
            throw new ReglaNegocio('Esta cita no tiene anticipo pendiente.');
        }
        if ($a->status === $estado) {
            return;
        }
        $anterior = $a->status;
        $a->status = $estado;
        if ($estado === 'Confirmada') {
            $a->confirmed = true;
        }
        $a->save();
        $a->registrar("Estado: {$anterior} a {$estado}");
        Auditoria::log('Cambio de estado', "{$a->patient->name}: {$anterior} a {$estado}", $a);
    }

    public static function pagar(Appointment $a, float $monto, string $metodo, ?string $referencia): void
    {
        $saldo = $a->deposit - $a->paid;
        if (! ($monto > 0)) {
            throw new ReglaNegocio('Escribe un monto mayor a cero.');
        }
        if ($monto > $saldo + 0.001) {
            throw new ReglaNegocio('El monto excede el saldo de '.Catalogo::dinero($saldo).'.');
        }
        DB::transaction(function () use ($a, $monto, $metodo, $referencia) {
            $a->payments()->create(['amount' => $monto, 'method' => $metodo, 'reference' => $referencia, 'user_name' => self::usuario(), 'paid_at' => now()]);
            $a->paid += $monto;
            $a->payment_method = $metodo;
            $a->registrar('Anticipo registrado: '.Catalogo::dinero($monto)." ({$metodo})");
            if ($a->status === 'Anticipo pendiente' && $a->paid >= $a->deposit) {
                $a->status = $a->confirmed ? 'Confirmada' : 'Agendada';
                $a->registrar('Anticipo liquidado');
            }
            $a->save();
            Auditoria::log('Anticipo registrado', "{$a->patient->name}: ".Catalogo::dinero($monto), $a);
            Mensajeria::enviar($a, 'anticipo');
        });
    }

    public static function reembolsar(Appointment $a, string $motivo): void
    {
        if ($a->isActive() || ! ($a->paid > 0) || $a->refunded) {
            throw new ReglaNegocio('Solo se reembolsan anticipos de citas canceladas o reagendadas.');
        }
        $a->update(['refunded' => true]);
        $a->registrar('Anticipo reembolsado: '.Catalogo::dinero($a->paid).". {$motivo}");
        Auditoria::log('Reembolso', "{$a->patient->name}: ".Catalogo::dinero($a->paid), $a);
    }

    public static function cancelar(Appointment $a, string $motivo, ?string $obs, bool $notificar): void
    {
        if (! $a->isActive() || in_array($a->status, ['Atendida', 'En consulta'], true)) {
            throw new ReglaNegocio('Esta cita no se puede cancelar.');
        }
        $a->update(['status' => 'Cancelada']);
        $a->registrar("Cita cancelada: {$motivo}".($obs ? ". {$obs}" : ''));
        Auditoria::log('Cita cancelada', "{$a->patient->name}, ".Catalogo::fechaCorta($a->dateString())." {$a->time}: {$motivo}", $a);
        if ($notificar) {
            Mensajeria::enviar($a, 'cancelacion');
        }
    }

    /** Traslada la cita a un nuevo horario conservando anticipos, respuestas, archivos e historial. */
    public static function reagendar(Appointment $a, int $doctorId, string $fecha, string $hora, string $motivo): Appointment
    {
        if (! $a->isActive() || in_array($a->status, ['Atendida', 'En consulta'], true)) {
            throw new ReglaNegocio('Esta cita no se puede reagendar.');
        }

        return DB::transaction(function () use ($a, $doctorId, $fecha, $hora, $motivo) {
            $a->loadMissing('form', 'payments', 'files', 'events', 'patient');
            $n = self::crear([
                'patient_id' => $a->patient_id, 'doctor_id' => $doctorId, 'date' => $fecha, 'time' => $hora, 'type' => $a->type,
                'reason' => $a->reason, 'cost' => $a->cost, 'deposit' => $a->deposit, 'paid' => 0, 'payment_method' => $a->payment_method,
                'notes' => $a->notes, 'channel' => $a->channel,
            ], false, $a);
            $n->fill([
                'paid' => $a->paid, 'status' => $a->deposit > $a->paid ? 'Anticipo pendiente' : 'Agendada',
                'hc_data' => $a->hc_data, 'hc_status' => $a->hc_status, 'hc_submitted_at' => $a->hc_submitted_at,
                'reviewed_by' => $a->reviewed_by, 'prev_asked' => $a->prev_asked,
            ])->save();
            foreach ($a->payments as $pg) {
                $n->payments()->create($pg->only('amount', 'method', 'reference', 'user_name', 'paid_at'));
            }
            foreach ($a->files as $f) {
                $n->files()->create($f->only('patient_id', 'field_id', 'original_name', 'path', 'mime', 'size_kb'));
            }
            foreach ($a->events as $e) {
                $n->events()->create(['text' => $e->text, 'user_name' => $e->user_name, 'created_at' => $e->created_at]);
            }
            $n->registrar('Reagendada desde '.Catalogo::fechaCorta($a->dateString())." {$a->time}: {$motivo}");
            $a->update(['status' => 'Reagendada', 'rescheduled_to_id' => $n->id]);
            $a->registrar('Cita trasladada a '.Catalogo::fechaCorta($n->dateString())." {$n->time}: {$motivo}");
            Auditoria::log('Cita reagendada', "{$a->patient->name}: ".Catalogo::fechaCorta($a->dateString())." {$a->time} a ".Catalogo::fechaCorta($n->dateString())." {$n->time}", $n);
            Mensajeria::enviar($n, 'reagenda');

            return $n;
        });
    }

    public static function finalizar(Appointment $a, ?string $nota): void
    {
        if ($a->status !== 'En consulta') {
            throw new ReglaNegocio('Primero inicia la consulta.');
        }
        $a->status = 'Atendida';
        $a->attention_note = $nota;
        if ($a->hc_status === 'Completado') {
            $a->hc_status = 'Revisado';
            $a->reviewed_by = self::usuario();
        }
        $a->save();
        $a->registrar('Atención concluida');
        Auditoria::log('Atención concluida', "{$a->patient->name} con {$a->doctor->name}", $a);
    }

    public static function editar(Appointment $a, array $o): void
    {
        $cambios = [];
        if ($o['type'] !== $a->type) {
            $cambios[] = "tipo {$a->type} a {$o['type']}";
            $a->type = $o['type'];
            if ($a->hc_status === 'Pendiente') {
                $f = Formatos::asignar($a->specialty_id, $a->type);
                $a->clinical_form_id = $f->id;
                $a->form_version = $f->currentVersion->version;
                $cambios[] = 'formato '.$f->name;
            }
        }
        if ((float) $o['cost'] !== $a->cost) {
            $cambios[] = 'costo '.Catalogo::dinero($a->cost).' a '.Catalogo::dinero($o['cost']);
            $a->cost = (float) $o['cost'];
        }
        $a->fill(['reason' => $o['reason'] ?? null, 'notes' => $o['notes'] ?? null, 'channel' => $o['channel']])->save();
        $txt = $cambios ? ': '.implode(', ', $cambios) : '';
        $a->registrar('Datos editados'.$txt);
        Auditoria::log('Cita editada', $a->patient->name.$txt, $a);
    }
}
