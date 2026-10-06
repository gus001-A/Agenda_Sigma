<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\ClinicalForm;
use App\Models\ClinicalFormVersion;
use App\Models\Patient;
use App\Models\Specialty;
use App\Support\Catalogo;
use Carbon\Carbon;

/** Reglas de formatos clínicos: asignación, avance, respuestas y reutilización. */
class Formatos
{
    /**
     * Busca el formato que corresponde a la especialidad y tipo de consulta:
     * 1) formato activo exacto, 2) formato subsecuente o de primera vez de la especialidad, 3) formato general.
     */
    public static function asignar(int $especialidadId, string $tipo): ClinicalForm
    {
        $exacto = ClinicalForm::where('active', true)->where('specialty_id', $especialidadId)->where('consult_type', $tipo)->first();
        if ($exacto) {
            return $exacto;
        }
        $esp = Specialty::find($especialidadId);
        if ($esp) {
            if (in_array($tipo, Catalogo::TIPOS_SUBSECUENTES, true) && $esp->sub_form_id && ($f = ClinicalForm::find($esp->sub_form_id))) {
                return $f;
            }
            if ($esp->first_form_id && ($f = ClinicalForm::find($esp->first_form_id))) {
                return $f;
            }
        }

        return ClinicalForm::whereNull('specialty_id')->where('consult_type', 'Primera consulta')->where('active', true)->first()
            ?? ClinicalForm::firstOrFail();
    }

    public static function version(Appointment $a): ?ClinicalFormVersion
    {
        if (! $a->clinical_form_id) {
            return null;
        }
        $form = $a->relationLoaded('form') ? $a->form : ClinicalForm::find($a->clinical_form_id);

        return $form?->version($a->form_version);
    }

    public static function secciones(Appointment $a): array
    {
        return self::version($a)?->sections ?? [];
    }

    public static function prellenado(array $campo, ?Patient $p): mixed
    {
        return match ($campo['prefill'] ?? null) {
            'name' => $p?->name,
            'birth' => $p?->birth_date?->format('Y-m-d'),
            'phone' => $p?->phone,
            default => null,
        };
    }

    public static function respondido(array $campo, mixed $v, array $archivos): bool
    {
        return match ($campo['type']) {
            'files', 'photo' => collect($archivos)->contains(fn ($f) => ($f['field_id'] ?? null) === $campo['id']),
            'allergies', 'meds' => is_array($v) && (! empty($v['none']) || collect($v['rows'] ?? [])->contains(fn ($r) => collect($r)->filter()->isNotEmpty())),
            'consent' => is_array($v) && ! empty($v['accepted']),
            'multi' => is_array($v) && count($v) > 0,
            'history' => is_array($v) && (count($v['sel'] ?? []) > 0 || ! empty($v['notes'])),
            'signature' => ! empty($v),
            default => $v !== null && trim((string) (is_array($v) ? json_encode($v) : $v)) !== '',
        };
    }

    private static function archivos(Appointment $a): array
    {
        return $a->files->map(fn ($f) => ['field_id' => $f->field_id])->all();
    }

    /** Porcentaje de avance del formulario (excluye campos de solo lectura). */
    public static function avance(Appointment $a, ?array $secciones = null): int
    {
        $secciones ??= self::secciones($a);
        $datos = $a->hc_data ?? [];
        $archivos = self::archivos($a);
        $total = 0;
        $hechos = 0;
        foreach ($secciones as $s) {
            foreach ($s['fields'] ?? [] as $f) {
                if (($f['mode'] ?? '') === 'readonly') {
                    continue;
                }
                $total++;
                if (self::respondido($f, $datos[$f['id']] ?? null, $archivos)) {
                    $hechos++;
                }
            }
        }

        return $total ? (int) round($hechos * 100 / $total) : 0;
    }

    /** Etiquetas de los campos obligatorios sin respuesta. */
    public static function faltantes(Appointment $a): array
    {
        $datos = $a->hc_data ?? [];
        $archivos = self::archivos($a);
        $out = [];
        foreach (self::secciones($a) as $s) {
            foreach ($s['fields'] ?? [] as $f) {
                if (($f['mode'] ?? '') !== 'required') {
                    continue;
                }
                $v = $datos[$f['id']] ?? self::prellenado($f, $a->patient);
                if (! self::respondido($f, $v, $archivos)) {
                    $out[] = $f['label'];
                }
            }
        }

        return $out;
    }

    /** Texto legible de una respuesta. */
    public static function texto(array $f, mixed $v, Appointment $a): string
    {
        if (($f['mode'] ?? '') === 'readonly' && ! empty($f['prefill'])) {
            $v = self::prellenado($f, $a->patient);
        }
        if (in_array($f['type'], ['files', 'photo'], true)) {
            return $a->files->where('field_id', $f['id'])->pluck('original_name')->implode(', ');
        }
        if ($v === null || $v === '' || $v === []) {
            return '';
        }

        return match ($f['type']) {
            'allergies' => ! empty($v['none']) ? 'Niega alergias' : collect($v['rows'] ?? [])->filter(fn ($r) => ! empty($r['d']))->map(fn ($r) => ($r['t'] ?? '').': '.$r['d'])->implode('; '),
            'meds' => ! empty($v['none']) ? 'No toma medicamentos' : collect($v['rows'] ?? [])->filter(fn ($r) => ! empty($r['m']))->map(fn ($r) => trim($r['m'].' '.($r['d'] ?? '').(! empty($r['f']) ? ' ('.$r['f'].')' : '')))->implode('; '),
            'multi' => implode(', ', (array) $v),
            'history' => collect([implode(', ', $v['sel'] ?? []), $v['notes'] ?? ''])->filter()->implode('. '),
            'consent' => ! empty($v['accepted']) ? 'Aceptado el '.Carbon::parse($v['at'])->format('d/m/Y H:i').' (formato v'.($v['v'] ?? '').')' : '',
            'signature' => 'Firmado',
            'date' => Carbon::parse($v)->format('d/m/Y'),
            'scale' => $v.' de 10',
            default => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string) $v,
        };
    }

    /** Historia clínica previa del paciente (la más reciente completada antes de esta cita). */
    public static function previa(Appointment $a): ?Appointment
    {
        return Appointment::query()->where('patient_id', $a->patient_id)->where('id', '!=', $a->id ?? 0)
            ->whereIn('hc_status', ['Completado', 'Revisado'])->whereNotNull('hc_data')
            ->where(fn ($q) => $q->where('date', '<', $a->dateString())
                ->orWhere(fn ($q) => $q->where('date', $a->dateString())->where('time', '<', $a->time)))
            ->orderByDesc('date')->orderByDesc('time')->first();
    }

    public static function campoPorTipo(array $secciones, string $tipo): ?array
    {
        foreach ($secciones as $s) {
            foreach ($s['fields'] ?? [] as $f) {
                if ($f['type'] === $tipo) {
                    return $f;
                }
            }
        }

        return null;
    }

    /** Resumen clínico para el médico (tarjeta de preconsulta). */
    public static function resumen(Appointment $a): array
    {
        $secs = self::secciones($a);
        $datos = $a->hc_data ?? [];
        $al = self::campoPorTipo($secs, 'allergies');
        $me = self::campoPorTipo($secs, 'meds');
        $alv = $al ? ($datos[$al['id']] ?? null) : null;
        $mev = $me ? ($datos[$me['id']] ?? null) : null;

        return [
            'paciente' => $a->patient->name,
            'edad' => $a->patient->age(),
            'motivo' => $datos['motivo'] ?? $a->reason ?: 'Sin especificar',
            'estado' => $a->hc_status,
            'alergias' => $alv ? (! empty($alv['none']) ? 'Niega' : self::texto($al, $alv, $a)) : 'Sin registrar',
            'alergias_alerta' => $alv && empty($alv['none']),
            'medicamentos' => $mev ? (! empty($mev['none']) ? 'Ninguno' : count($mev['rows'] ?? []).' registrados') : 'Sin registrar',
            'documentos' => $a->files->count(),
            'version' => $a->form_version,
        ];
    }

    /** Respuestas por sección para mostrar al personal autorizado. */
    public static function respuestas(Appointment $a): array
    {
        $datos = $a->hc_data ?? [];

        return collect(self::secciones($a))->map(fn ($s) => [
            'title' => $s['title'],
            'fields' => collect($s['fields'] ?? [])->map(function ($f) use ($datos, $a) {
                $v = $datos[$f['id']] ?? null;
                $item = ['id' => $f['id'], 'label' => $f['label'], 'type' => $f['type'], 'mode' => $f['mode'] ?? 'optional', 'text' => self::texto($f, $v, $a)];
                if ($f['type'] === 'signature') {
                    $item['signature'] = is_string($v) ? $v : null;
                }
                if (in_array($f['type'], ['files', 'photo'], true)) {
                    $item['files'] = $a->files->where('field_id', $f['id'])->map->resumen()->values();
                }

                return $item;
            })->all(),
        ])->all();
    }

    /** Siguiente número de versión (menor o mayor). */
    public static function siguienteVersion(string $actual, bool $mayor): string
    {
        [$ma, $mi] = array_map('intval', explode('.', $actual) + [1, 0]);

        return $mayor ? ($ma + 1).'.0' : $ma.'.'.($mi + 1);
    }
}
