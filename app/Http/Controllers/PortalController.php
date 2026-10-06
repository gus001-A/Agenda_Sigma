<?php

namespace App\Http\Controllers;

use App\Exceptions\ReglaNegocio;
use App\Models\Appointment;
use App\Models\AppointmentFile;
use App\Services\Auditoria;
use App\Services\Citas;
use App\Services\Formatos;
use App\Support\Catalogo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Portal del paciente (enlace seguro único por cita, sin usuario ni contraseña).
 * Permite confirmar asistencia y completar la historia clínica previa a la consulta.
 */
class PortalController extends Controller
{
    private function cita(string $token): Appointment
    {
        return Appointment::with('patient', 'doctor.room', 'specialty', 'form', 'files')->where('token', $token)->firstOrFail();
    }

    private function editable(Appointment $a): bool
    {
        return $a->isActive() && $a->status !== 'Atendida' && ! in_array($a->hc_status, ['Completado', 'Revisado'], true);
    }

    private function asegurarEditable(Appointment $a): void
    {
        if (! $this->editable($a)) {
            throw new ReglaNegocio('La historia clínica ya fue enviada o la cita no está activa.');
        }
    }

    public function show(Request $request, string $token): Response
    {
        $a = $this->cita($token);
        // Marca como leídos los mensajes enviados al paciente.
        $a->messages()->whereNull('read_at')->where('channel', '!=', 'Sistema')->update(['status' => 'Leído', 'read_at' => now()]);
        $clave = 'portal_visto_'.$a->id;
        if (! $request->session()->has($clave)) {
            $request->session()->put($clave, true);
            Auditoria::log('Enlace abierto por paciente', $a->patient->name, $a, usuario: $a->patient->name);
        }
        $p = $a->patient;
        $r = $a->doctor->room;
        $secciones = Formatos::secciones($a);
        $datos = $a->hc_data ?? [];
        // Valores prellenados desde el expediente (nombre, nacimiento, teléfono).
        foreach ($secciones as $s) {
            foreach ($s['fields'] as $f) {
                if (! empty($f['prefill']) && ! array_key_exists($f['id'], $datos)) {
                    $datos[$f['id']] = Formatos::prellenado($f, $p);
                }
            }
        }
        $previa = $a->hc_status === 'Pendiente' && ! $a->prev_asked ? Formatos::previa($a) : null;

        return Inertia::render('Portal', [
            'token' => $token,
            'clinica' => config('app.clinica'),
            'cita' => [
                'patient' => $p->name, 'first_name' => explode(' ', $p->name)[0],
                'doctor' => $a->doctor->name, 'specialty' => $a->specialty->name,
                'date' => $a->dateString(), 'date_label' => Catalogo::fechaLarga($a->dateString()), 'time' => $a->time,
                'place' => $r ? "{$r->name}, {$r->location}" : null, 'type' => $a->type,
                'status' => $a->status, 'active' => $a->isActive(), 'confirmed' => $a->confirmed,
                'balance' => $a->refunded ? 0 : $a->depositBalance(),
                'hc_status' => $a->hc_status, 'update_request' => $a->hc_status === 'Requiere actualización' ? $a->update_request : null,
                'form' => $a->form?->name, 'version' => $a->form_version,
                'progress' => Formatos::avance($a, $secciones),
                'editable' => $this->editable($a),
            ],
            'secciones' => $secciones,
            'respuestas' => (object) $datos,
            'archivos' => $a->files->map->resumen()->values(),
            'previa' => $previa ? ['date' => $previa->dateString(), 'label' => Catalogo::fechaLarga($previa->dateString())] : null,
        ]);
    }

    public function confirmar(string $token): JsonResponse
    {
        $a = $this->cita($token);
        if (! $a->isActive() || $a->status === 'Atendida') {
            throw new ReglaNegocio('Esta cita ya no está activa.');
        }
        Citas::confirmar($a, true);

        return response()->json(['ok' => true, 'message' => 'Asistencia confirmada']);
    }

    /** Limpia las respuestas: solo campos del formato, sin campos de solo lectura ni archivos. */
    private function sanitizar(Appointment $a, array $entrada): array
    {
        $datos = $a->hc_data ?? [];
        foreach (Formatos::secciones($a) as $s) {
            foreach ($s['fields'] as $f) {
                $id = $f['id'];
                if (($f['mode'] ?? '') === 'readonly' || in_array($f['type'], ['files', 'photo'], true) || ! array_key_exists($id, $entrada)) {
                    continue;
                }
                $v = $entrada[$id];
                $v = match ($f['type']) {
                    'number' => $v === '' || $v === null ? null : (is_numeric($v) ? $v + 0 : null),
                    'scale' => $v === '' || $v === null ? null : max(0, min(10, (int) $v)),
                    'yesno', 'single', 'select' => in_array($v, $f['type'] === 'yesno' ? ['Sí', 'No'] : $f['options'], true) ? $v : null,
                    'multi' => array_values(array_intersect((array) $v, $f['options'])),
                    'history' => ['sel' => array_values(array_intersect((array) ($v['sel'] ?? []), $f['options'])), 'notes' => mb_substr((string) ($v['notes'] ?? ''), 0, 1000)],
                    'allergies' => ['none' => (bool) ($v['none'] ?? false), 'rows' => collect($v['rows'] ?? [])->map(fn ($r) => ['t' => mb_substr((string) ($r['t'] ?? 'Otro'), 0, 30), 'd' => mb_substr(trim((string) ($r['d'] ?? '')), 0, 255)])->filter(fn ($r) => $r['d'] !== '')->values()->all()],
                    'meds' => ['none' => (bool) ($v['none'] ?? false), 'rows' => collect($v['rows'] ?? [])->map(fn ($r) => ['m' => mb_substr(trim((string) ($r['m'] ?? '')), 0, 120), 'd' => mb_substr(trim((string) ($r['d'] ?? '')), 0, 60), 'f' => mb_substr(trim((string) ($r['f'] ?? '')), 0, 60)])->filter(fn ($r) => $r['m'] !== '')->values()->all()],
                    'consent' => ! empty($v['accepted']) ? (! empty($datos[$id]['accepted']) ? $datos[$id] : ['accepted' => true, 'at' => now()->toIso8601String(), 'v' => $a->form_version]) : null,
                    'signature' => is_string($v) && str_starts_with($v, 'data:image/png;base64,') && strlen($v) < 400000 ? $v : null,
                    default => $v === null ? null : mb_substr(trim((string) $v), 0, 5000),
                };
                if ($v === null || $v === '' || $v === []) {
                    unset($datos[$id]);
                } else {
                    $datos[$id] = $v;
                }
            }
        }

        return $datos;
    }

    public function guardar(Request $request, string $token): JsonResponse
    {
        $a = $this->cita($token);
        $this->asegurarEditable($a);
        $request->validate(['respuestas' => 'present|array', 'explicito' => 'boolean']);
        $a->hc_data = $this->sanitizar($a, $request->input('respuestas', []));
        if ($a->hc_status === 'Pendiente') {
            $a->hc_status = 'En proceso';
        }
        $a->save();
        $avance = Formatos::avance($a);
        if ($request->boolean('explicito')) {
            $a->registrar('Paciente guardó avance de historia clínica', $a->patient->name);
            Auditoria::log('Avance de historia clínica', "{$a->patient->name}: {$avance}%", $a, usuario: $a->patient->name);
        }

        return response()->json(['ok' => true, 'progress' => $avance, 'message' => 'Avance guardado. Puede continuar cuando quiera desde el mismo enlace.']);
    }

    public function enviar(Request $request, string $token): JsonResponse
    {
        $a = $this->cita($token);
        $this->asegurarEditable($a);
        if ($request->has('respuestas')) {
            $a->hc_data = $this->sanitizar($a, $request->input('respuestas', []));
            $a->save();
        }
        $faltan = Formatos::faltantes($a);
        if ($faltan) {
            return response()->json(['message' => 'Faltan campos obligatorios: '.implode(', ', array_slice($faltan, 0, 3)), 'faltan' => $faltan], 422);
        }
        $a->update(['hc_status' => 'Completado', 'hc_submitted_at' => now(), 'update_request' => null]);
        $a->registrar("Historia clínica enviada por el paciente ({$a->form->name} v{$a->form_version})", $a->patient->name);
        Auditoria::log('Historia clínica completada', "{$a->patient->name}, formato v{$a->form_version}, {$a->files->count()} archivos", $a, usuario: $a->patient->name);

        return response()->json(['ok' => true, 'message' => 'Información enviada']);
    }

    /** "¿La información registrada anteriormente continúa vigente?" */
    public function reutilizar(Request $request, string $token): JsonResponse
    {
        $a = $this->cita($token);
        $this->asegurarEditable($a);
        $modo = $request->validate(['modo' => 'required|in:keep,edit,new'])['modo'];
        $a->prev_asked = true;
        if ($modo !== 'new' && ($pr = Formatos::previa($a))) {
            $ids = collect(Formatos::secciones($a))->flatMap(fn ($s) => array_column($s['fields'], 'id'))->all();
            $datos = $a->hc_data ?? [];
            foreach ($pr->hc_data as $k => $v) {
                if (in_array($k, $ids, true) && ! in_array($k, ['motivo', 'padec', 'privacidad', 'firma'], true)) {
                    $datos[$k] = $v;
                }
            }
            $a->hc_data = $datos;
            $a->hc_status = 'En proceso';
            $fecha = Catalogo::fechaCorta($pr->dateString());
            $a->registrar('Paciente '.($modo === 'keep' ? 'confirmó como vigente' : 'reutilizó y modificará')." su información del {$fecha}", $a->patient->name);
            Auditoria::log('Reutilización de historia clínica', "{$a->patient->name}: información del {$fecha}", $a, usuario: $a->patient->name);
        }
        $a->save();

        return response()->json(['ok' => true]);
    }

    public function subirArchivo(Request $request, string $token): JsonResponse
    {
        $a = $this->cita($token);
        $this->asegurarEditable($a);
        $v = $request->validate(['field_id' => 'required|string|max:40', 'archivo' => 'required|file|max:10240|mimes:jpg,jpeg,png,webp,heic,pdf'],
            ['archivo.max' => 'El archivo no debe pesar más de 10 MB.', 'archivo.mimes' => 'Solo se aceptan PDF o imágenes.']);
        $campo = collect(Formatos::secciones($a))->flatMap(fn ($s) => $s['fields'])->first(fn ($f) => $f['id'] === $v['field_id'] && in_array($f['type'], ['files', 'photo'], true));
        if (! $campo) {
            throw new ReglaNegocio('Campo de archivo no válido.');
        }
        $file = $request->file('archivo');
        $ruta = $file->store("clinico/{$a->patient_id}/{$a->id}", 'local');
        $f = $a->files()->create(['patient_id' => $a->patient_id, 'field_id' => $campo['id'], 'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'path' => $ruta, 'mime' => $file->getMimeType(), 'size_kb' => max(1, (int) round($file->getSize() / 1024))]);
        if ($a->hc_status === 'Pendiente') {
            $a->update(['hc_status' => 'En proceso']);
        }
        Auditoria::log('Documento adjuntado', "{$f->original_name} de {$a->patient->name}", $a, usuario: $a->patient->name);

        return response()->json(['ok' => true, 'archivo' => $f->resumen()]);
    }

    public function borrarArchivo(string $token, AppointmentFile $archivo): JsonResponse
    {
        $a = $this->cita($token);
        $this->asegurarEditable($a);
        abort_unless($archivo->appointment_id === $a->id, 404);
        // El archivo físico puede estar compartido con una cita reagendada; solo se elimina si nadie más lo usa.
        if ($archivo->path && AppointmentFile::where('path', $archivo->path)->count() === 1) {
            Storage::disk('local')->delete($archivo->path);
        }
        $archivo->delete();

        return response()->json(['ok' => true]);
    }
}
