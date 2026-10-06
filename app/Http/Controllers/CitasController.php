<?php

namespace App\Http\Controllers;

use App\Exceptions\ReglaNegocio;
use App\Models\Appointment;
use App\Models\AppointmentFile;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\WaitlistEntry;
use App\Services\Agenda;
use App\Services\Auditoria;
use App\Services\Citas;
use App\Services\Formatos;
use App\Services\Mensajeria;
use App\Support\Catalogo;
use App\Support\Presentador;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Acciones sobre citas (JSON) usadas por el panel de detalle y los diálogos. */
class CitasController extends Controller
{
    /** El médico solo puede consultar a sus propios pacientes. */
    private function autorizarClinico(Request $request, Appointment $a): void
    {
        $u = $request->user();
        abort_unless($u->can_('verclinico'), 403, 'Tu rol no tiene acceso al contenido clínico.');
        abort_if($u->isDoctor() && $u->doctor_id !== $a->doctor_id, 403, 'Solo puedes consultar a tus pacientes.');
    }

    private function ok(string $mensaje, array $extra = []): JsonResponse
    {
        return response()->json(['ok' => true, 'message' => $mensaje] + $extra);
    }

    public function show(Request $request, Appointment $cita): JsonResponse
    {
        abort_if($request->user()->isDoctor() && $request->user()->doctor_id !== $cita->doctor_id, 403, 'Solo puedes consultar a tus pacientes.');

        return response()->json(Presentador::detalle($cita, $request->user()));
    }

    public function store(Request $request): JsonResponse
    {
        $v = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'doctor_id' => 'required|exists:doctors,id',
            'date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'time' => 'required|date_format:H:i',
            'type' => ['required', Rule::in(Catalogo::TIPOS_CONSULTA)],
            'reason' => 'nullable|string|max:255',
            'cost' => 'required|numeric|min:0',
            'deposit' => 'nullable|numeric|min:0',
            'paid' => 'nullable|numeric|min:0',
            'payment_method' => ['nullable', Rule::in(Catalogo::FORMAS_PAGO)],
            'channel' => ['required', Rule::in(Catalogo::CANALES)],
            'notes' => 'nullable|string|max:255',
            'waitlist_id' => 'nullable|exists:waitlist_entries,id',
        ], [], ['patient_id' => 'paciente', 'doctor_id' => 'médico', 'date' => 'fecha', 'time' => 'horario']);

        $a = Citas::crear($v);
        if (! empty($v['waitlist_id'])) {
            $w = WaitlistEntry::find($v['waitlist_id']);
            $w->update(['status' => 'Agendado', 'appointment_id' => $a->id]);
            Auditoria::log('Lista de espera atendida', $w->patient->name.' agendado', $a);
        }

        return $this->ok("Cita registrada para {$a->patient->name}. Notificación enviada por {$a->channel}.", ['id' => $a->id]);
    }

    public function update(Request $request, Appointment $cita): JsonResponse
    {
        $v = $request->validate([
            'type' => ['required', Rule::in(Catalogo::TIPOS_CONSULTA)],
            'cost' => 'required|numeric|min:0',
            'reason' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:255',
            'channel' => ['required', Rule::in(Catalogo::CANALES)],
        ]);
        Citas::editar($cita, $v);

        return $this->ok('Cambios guardados');
    }

    public function confirmar(Appointment $cita): JsonResponse
    {
        Citas::confirmar($cita);

        return $this->ok($cita->status === 'Anticipo pendiente' ? 'Confirmada. El anticipo sigue pendiente.' : 'Cita confirmada');
    }

    public function estado(Request $request, Appointment $cita): JsonResponse
    {
        $v = $request->validate(['status' => ['required', Rule::in(Catalogo::ESTADOS_MANUALES)]]);
        $u = $request->user();
        // Iniciar consulta lo puede hacer quien tenga permiso de "consulta"; el resto de cambios requiere "cita".
        $permitido = $v['status'] === 'En consulta' ? $u->can_('consulta') : $u->can_('cita');
        abort_unless($permitido, 403, 'Tu rol no permite este cambio de estado.');
        abort_if($u->isDoctor() && $u->doctor_id !== $cita->doctor_id, 403, 'Solo puedes atender a tus pacientes.');
        Citas::cambiarEstado($cita, $v['status']);

        return $this->ok("Estado actualizado a {$v['status']}");
    }

    public function pagar(Request $request, Appointment $cita): JsonResponse
    {
        $v = $request->validate(['amount' => 'required|numeric', 'method' => ['required', Rule::in(Catalogo::FORMAS_PAGO)], 'reference' => 'nullable|string|max:100']);
        Citas::pagar($cita, (float) $v['amount'], $v['method'], $v['reference'] ?? null);
        $cita->refresh();

        return $this->ok($cita->paid >= $cita->deposit ? 'Anticipo liquidado' : 'Pago parcial registrado. Saldo '.Catalogo::dinero($cita->deposit - $cita->paid));
    }

    public function reembolsar(Request $request, Appointment $cita): JsonResponse
    {
        $v = $request->validate(['reason' => 'nullable|string|max:255']);
        Citas::reembolsar($cita, $v['reason'] ?? 'Sin motivo');

        return $this->ok('Reembolso registrado');
    }

    public function cancelar(Request $request, Appointment $cita): JsonResponse
    {
        $v = $request->validate(['reason' => ['required', Rule::in(Catalogo::MOTIVOS_CANCELACION)], 'notes' => 'nullable|string|max:255', 'notify' => 'boolean']);
        Citas::cancelar($cita, $v['reason'], $v['notes'] ?? null, (bool) ($v['notify'] ?? true));

        return $this->ok('Cita cancelada. El horario quedó disponible.', ['liberado' => $this->liberado($cita->doctor, $cita->dateString(), $cita->time)]);
    }

    public function reagendar(Request $request, Appointment $cita): JsonResponse
    {
        $v = $request->validate(['doctor_id' => 'required|exists:doctors,id', 'date' => 'required|date_format:Y-m-d|after_or_equal:today', 'time' => 'required|date_format:H:i', 'reason' => 'nullable|string|max:255']);
        $anterior = ['doctor' => $cita->doctor, 'date' => $cita->dateString(), 'time' => $cita->time];
        $n = Citas::reagendar($cita, $v['doctor_id'], $v['date'], $v['time'], $v['reason'] ?: 'Solicitud del paciente');

        return $this->ok('Cita reagendada y paciente notificado', ['id' => $n->id, 'liberado' => $this->liberado($anterior['doctor'], $anterior['date'], $anterior['time'])]);
    }

    /** Si el espacio liberado tiene candidatos compatibles en lista de espera, se devuelven para ofrecerlos. */
    private function liberado(Doctor $d, string $fecha, string $hora): ?array
    {
        if ($fecha < now()->format('Y-m-d')) {
            return null;
        }
        $c = Agenda::candidatos($d, $fecha, $hora);
        if ($c->isEmpty()) {
            return null;
        }

        return ['doctor_id' => $d->id, 'doctor' => $d->name, 'date' => $fecha, 'time' => $hora,
            'candidatos' => $c->map(fn ($w) => ['id' => $w->id, 'patient_id' => $w->patient_id, 'patient' => $w->patient->name, 'notes' => $w->notes,
                'priority' => $w->priority, 'preference' => $w->preference, 'requested_at' => $w->requested_at->format('Y-m-d')])->values()];
    }

    public function finalizar(Request $request, Appointment $cita): JsonResponse
    {
        abort_if($request->user()->isDoctor() && $request->user()->doctor_id !== $cita->doctor_id, 403);
        $v = $request->validate(['note' => 'nullable|string|max:5000', 'followup' => 'boolean']);
        Citas::finalizar($cita, $v['note'] ?? null);
        $sig = null;
        if (! empty($v['followup']) && $request->user()->can_('cita')) {
            $r = (new Agenda)->proximos(null, $cita->doctor_id, Agenda::sumarDias(now()->format('Y-m-d'), 7), 45, '', 1)[0] ?? null;
            $sig = $r ? ['doctor_id' => $cita->doctor_id, 'date' => $r['date'], 'time' => $r['times'][0], 'patient_id' => $cita->patient_id] : null;
        }

        return $this->ok('Atención registrada en el expediente', ['seguimiento' => $sig]);
    }

    public function reenviar(Appointment $cita): JsonResponse
    {
        Mensajeria::enviar($cita, 'registro');
        $cita->registrar('Enlace reenviado por '.$cita->channel);
        Auditoria::log('Notificación reenviada', $cita->patient->name, $cita);

        return $this->ok("Enlace reenviado por {$cita->channel}");
    }

    /** Teléfono en formato internacional para WhatsApp/SMS (México: 52 + 10 dígitos). */
    private function telefonoInternacional(?string $tel): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $tel);
        if ($d === '') {
            return null;
        }

        return strlen($d) === 10 ? '52'.$d : $d;
    }

    /** Mensaje prellenado para enviar manualmente por WhatsApp, SMS o correo. */
    public function mensaje(Request $request, Appointment $cita): JsonResponse
    {
        $tipo = in_array($request->tipo, ['registro', 'r24', 'historia', 'cobro', 'manual'], true) ? $request->tipo : 'registro';
        $cita->loadMissing('patient');

        return response()->json([
            'texto' => Mensajeria::texto($cita, $tipo),
            'telefono' => $cita->patient->phone,
            'telefono_int' => $this->telefonoInternacional($cita->patient->phone),
            'email' => $cita->patient->email,
            'patient' => $cita->patient->name,
            'portal_url' => $cita->portalUrl(),
        ]);
    }

    /** Registra el envío manual (el mensaje se abre en WhatsApp/SMS/correo desde el navegador). */
    public function registrarMensaje(Request $request, Appointment $cita): JsonResponse
    {
        $v = $request->validate([
            'canal' => ['required', Rule::in(['WhatsApp', 'SMS', 'Correo electrónico'])],
            'tipo' => ['required', Rule::in(['registro', 'r24', 'historia', 'cobro', 'manual'])],
            'texto' => 'required|string|max:2000',
        ], [], ['texto' => 'mensaje']);
        $cita->messages()->create(['channel' => $v['canal'], 'kind' => $v['tipo'], 'body' => $v['texto'], 'status' => 'Enviado', 'sent_at' => now()]);
        $etiqueta = Catalogo::ETIQUETAS_MENSAJE[$v['tipo']] ?? 'Mensaje';
        $cita->registrar("{$etiqueta} enviado por {$v['canal']}");
        Auditoria::log('Mensaje enviado', "{$cita->patient->name}: {$etiqueta} por {$v['canal']}", $cita);

        return $this->ok("Mensaje registrado. Se abrió {$v['canal']} con el texto listo para enviar.");
    }

    public function historia(Request $request, Appointment $cita): JsonResponse
    {
        $this->autorizarClinico($request, $cita);
        $cita->load('patient', 'files', 'form');
        Auditoria::log('Consulta de historia clínica', "{$cita->patient->name} ({$cita->form?->name} v{$cita->form_version})", $cita);
        $u = $request->user();

        return response()->json([
            'id' => $cita->id,
            'patient' => $cita->patient->name,
            'hc_status' => $cita->hc_status,
            'resumen' => Formatos::resumen($cita),
            'secciones' => Formatos::respuestas($cita),
            'puedeRevisar' => $u->can_('clinico') && in_array($cita->hc_status, ['Completado', 'Requiere actualización'], true),
        ]);
    }

    public function revisada(Request $request, Appointment $cita): JsonResponse
    {
        $this->autorizarClinico($request, $cita);
        $cita->update(['hc_status' => 'Revisado', 'reviewed_by' => $request->user()->name]);
        $cita->registrar('Historia clínica revisada');
        Auditoria::log('Historia clínica revisada', $cita->patient->name, $cita);

        return $this->ok('Historia clínica marcada como revisada');
    }

    public function actualizacion(Request $request, Appointment $cita): JsonResponse
    {
        $this->autorizarClinico($request, $cita);
        $v = $request->validate(['request' => 'required|string|max:1000'], [], ['request' => 'información solicitada']);
        if ($cita->status === 'Atendida' || $cita->hc_status === 'Pendiente') {
            throw new ReglaNegocio('No se puede solicitar actualización en esta cita.');
        }
        $cita->update(['hc_status' => 'Requiere actualización', 'update_request' => $v['request']]);
        $cita->registrar('Se solicitó actualización: '.$v['request']);
        Mensajeria::enviar($cita, 'actualizar');
        Auditoria::log('Actualización solicitada', $cita->patient->name, $cita);

        return $this->ok('Solicitud enviada al paciente');
    }

    /** Descarga/visualización de un documento clínico (acceso registrado en auditoría). */
    public function archivo(Request $request, AppointmentFile $archivo)
    {
        $cita = Appointment::findOrFail($archivo->appointment_id);
        $this->autorizarClinico($request, $cita);
        Auditoria::log('Consulta de documento', "{$archivo->original_name} de {$cita->patient->name}", $cita);
        if ($request->boolean('info') || ! $archivo->path || ! Storage::disk('local')->exists($archivo->path)) {
            return response()->json(['id' => $archivo->id, 'name' => $archivo->original_name, 'size' => $archivo->size_kb, 'image' => $archivo->isImage(),
                'exists' => (bool) ($archivo->path && Storage::disk('local')->exists($archivo->path)), 'at' => $archivo->created_at?->toIso8601String()]);
        }

        return Storage::disk('local')->response($archivo->path, $archivo->original_name);
    }

    // ---------------- auxiliares del diálogo "Nueva cita" ----------------

    /** Horarios disponibles de un médico en una fecha. */
    public function horarios(Request $request): JsonResponse
    {
        $request->validate(['doctor_id' => 'required|exists:doctors,id', 'date' => 'required|date_format:Y-m-d']);
        $d = Doctor::findOrFail($request->doctor_id);
        $agenda = new Agenda($request->date, $request->date);

        return response()->json([
            'works' => $d->worksOn($request->date),
            'days' => $d->days,
            'times' => $request->date < now()->format('Y-m-d') ? [] : $agenda->disponibles($d, $request->date),
        ]);
    }

    /** Próxima fecha con espacio para un médico. */
    public function proxima(Request $request): JsonResponse
    {
        $request->validate(['doctor_id' => 'required|exists:doctors,id', 'from' => 'nullable|date_format:Y-m-d']);
        $r = (new Agenda)->proximos(null, (int) $request->doctor_id, $request->from ?? now()->format('Y-m-d'), 60, '', 1)[0] ?? null;

        return $r ? response()->json(['date' => $r['date'], 'times' => $r['times']])
            : response()->json(['message' => 'Sin disponibilidad en los próximos 60 días.'], 422);
    }

    /** Formato clínico que se asignará y tipo de consulta sugerido. */
    public function formato(Request $request): JsonResponse
    {
        $request->validate(['doctor_id' => 'required|exists:doctors,id', 'type' => 'nullable|string', 'patient_id' => 'nullable|exists:patients,id', 'date' => 'nullable|date']);
        $d = Doctor::with('specialty')->findOrFail($request->doctor_id);
        $sugerido = null;
        $previa = null;
        if ($request->patient_id) {
            $atendido = Appointment::where('patient_id', $request->patient_id)->where('specialty_id', $d->specialty_id)
                ->where('date', '<', $request->date ?? now()->format('Y-m-d'))->where('status', 'Atendida')->exists();
            $sugerido = $d->specialty->name === 'Fisioterapia' ? 'Terapia' : ($atendido ? 'Consulta subsecuente' : 'Primera consulta');
            $p = Appointment::where('patient_id', $request->patient_id)->whereIn('hc_status', ['Completado', 'Revisado'])->whereNotNull('hc_data')->orderByDesc('date')->first();
            $previa = $p?->dateString();
        }
        $f = Formatos::asignar($d->specialty_id, $request->type ?: ($sugerido ?? 'Primera consulta'));

        return response()->json(['form' => $f->name, 'version' => $f->currentVersion->version, 'sugerido' => $sugerido, 'previa' => $previa]);
    }

    public function buscarPacientes(Request $request): JsonResponse
    {
        $q = trim((string) $request->q);
        $tel = preg_replace('/\s+/', '', $q);
        $hoy = now()->format('Y-m-d');

        return response()->json(Patient::query()
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', "%{$q}%")->orWhere('sigma_id', 'like', "%{$q}%")
                ->orWhereRaw("REPLACE(phone, ' ', '') like ?", ["%{$tel}%"])))
            ->orderBy('name')->limit(8)->withCount(['appointments as previas' => fn ($x) => $x->where('date', '<', $hoy)])->get()
            ->map(fn ($p) => $p->resumen() + ['previas' => $p->previas]));
    }
}
