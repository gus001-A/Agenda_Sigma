<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\PatientMessage;
use App\Models\ReminderRule;
use App\Models\Setting;
use App\Support\Catalogo;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Notificaciones al paciente (WhatsApp, SMS, correo y sistema).
 *
 * Los proveedores externos se configuran en config/mensajeria.php. Con el driver "log"
 * el mensaje se registra en el log de Laravel y se marca como entregado; el correo
 * electrónico usa el mailer configurado de Laravel.
 */
class Mensajeria
{
    public static function canales(): array
    {
        return Setting::get('canales', array_fill_keys(Catalogo::CANALES, true));
    }

    public static function texto(Appointment $a, string $tipo): string
    {
        $a->loadMissing('patient', 'doctor.room', 'specialty');
        $p = $a->patient;
        $d = $a->doctor;
        $r = $d->room;
        $nombre = explode(' ', $p->name)[0];
        $enlace = $a->portalUrl();
        $cuando = Catalogo::fechaLarga($a->dateString()).' a las '.$a->time;
        $recordatorio = fn (string $c) => ReminderRule::where('code', $c)->value('text') ?? '';
        $saldo = $a->deposit > $a->paid && ! $a->refunded ? "\nAnticipo pendiente: ".Catalogo::dinero($a->deposit - $a->paid) : '';

        return match ($tipo) {
            'registro' => "Hola {$nombre}, su cita en ".config('app.clinica')." quedó registrada.\n\n{$d->name} ({$a->specialty->name})\n{$cuando}\n"
                .($r ? "{$r->name}, {$r->location}\n" : '')."\nPor favor llegue 15 minutos antes.{$saldo}\n\nConfirme su asistencia y complete su historia clínica aquí:\n{$enlace}",
            'reagenda' => "Hola {$nombre}, su cita con {$d->name} se cambió al {$cuando}. Sus respuestas de historia clínica se conservan.\n{$enlace}",
            'cancelacion' => "Hola {$nombre}, su cita con {$d->name} del {$cuando} fue cancelada. Si desea una nueva fecha, responda este mensaje.",
            'r72' => "Hola {$nombre}. ".$recordatorio('r72')."\nCita: {$cuando}\n{$enlace}",
            'r24' => "Hola {$nombre}. ".$recordatorio('r24')."\n{$d->name}, {$a->time}\n{$enlace}",
            'r0' => "Hola {$nombre}. ".$recordatorio('r0')."\n{$d->name}, {$a->time}".($r ? " en {$r->name}" : ''),
            'actualizar' => "Hola {$nombre}, su médico solicita actualizar algunos datos de su historia clínica antes de la consulta del {$cuando}.\n{$enlace}",
            'confirmacion' => "{$p->name} confirmó su asistencia a la cita del {$cuando}.",
            'anticipo' => "Hola {$nombre}, recibimos su anticipo para la cita del {$cuando}. Gracias.",
            default => '',
        };
    }

    public static function enviar(Appointment $a, string $tipo, ?string $canal = null): PatientMessage
    {
        $canal = $canal ?: ($a->channel ?: 'WhatsApp');
        if (empty(self::canales()[$canal])) {
            $canal = 'Sistema';
        }
        $msg = $a->messages()->create([
            'channel' => $canal, 'kind' => $tipo, 'body' => self::texto($a, $tipo), 'status' => 'Enviado', 'sent_at' => now(),
        ]);

        try {
            self::despachar($msg, $a);
        } catch (\Throwable $e) {
            $msg->update(['status' => 'Error', 'error' => mb_substr($e->getMessage(), 0, 250)]);
            Log::warning('Mensajería: '.$e->getMessage());
        }

        return $msg;
    }

    private static function despachar(PatientMessage $msg, Appointment $a): void
    {
        $driver = config('mensajeria.canales.'.$msg->channel, 'log');
        if ($msg->channel === 'Sistema') {
            $msg->update(['status' => 'Entregado']);

            return;
        }
        if ($msg->channel === 'Correo electrónico' && $driver === 'mail') {
            if (! $a->patient->email) {
                throw new \RuntimeException('El paciente no tiene correo registrado.');
            }
            Mail::raw($msg->body, fn ($m) => $m->to($a->patient->email)->subject(config('app.clinica').': '.(Catalogo::ETIQUETAS_MENSAJE[$msg->kind] ?? 'Aviso')));
            $msg->update(['status' => 'Entregado']);

            return;
        }
        // Driver "log": simulación hasta definir el proveedor de WhatsApp Business / SMS.
        Log::info("[{$msg->channel}] a {$a->patient->phone}: ".$msg->body);
        $msg->update(['status' => 'Entregado']);
    }

    /** Envía los recordatorios que correspondan (72 h, 24 h y día de la cita). Devuelve cuántos se enviaron. */
    public static function recordatorios(): int
    {
        $reglas = ReminderRule::where('active', true)->get()->keyBy('code');
        $hoy = now()->format('Y-m-d');
        $n = 0;
        $citas = Appointment::query()->active()->with('patient', 'doctor.room', 'specialty')
            ->where('status', '!=', 'Atendida')->whereBetween('date', [$hoy, Agenda::sumarDias($hoy, 3)])->get();
        foreach ($citas as $a) {
            $momento = Carbon::parse($a->dateString().' '.$a->time);
            $horas = now()->diffInMinutes($momento, false) / 60;
            if ($horas < 0) {
                continue;
            }
            $codigo = $a->dateString() === $hoy ? 'r0' : ($horas <= 24 ? 'r24' : ($horas <= 72 ? 'r72' : null));
            $regla = $codigo ? $reglas->get($codigo) : null;
            $enviados = $a->reminders_sent ?? [];
            if (! $regla || isset($enviados[$codigo])) {
                continue;
            }
            if ($regla->only_if_hc_pending && ! in_array($a->hc_status, ['Pendiente', 'En proceso', 'Requiere actualización'], true)) {
                continue;
            }
            self::enviar($a, $codigo);
            $enviados[$codigo] = now()->toIso8601String();
            $a->update(['reminders_sent' => $enviados]);
            $a->registrar(Catalogo::ETIQUETAS_MENSAJE[$codigo].' enviado', 'Sistema');
            $n++;
        }

        return $n;
    }
}
