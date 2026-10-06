<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\ClinicalForm;
use App\Services\Auditoria;
use App\Services\Formatos;
use App\Support\Catalogo;
use Database\Seeders\PlantillasFormatos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Constructor de formatos clínicos con borrador y versionado. */
class FormatosController extends Controller
{
    public function index(Request $request): Response
    {
        $formas = ClinicalForm::with('specialty:id,name', 'currentVersion')->orderBy('id')->get();
        $f = $formas->firstWhere('id', $request->integer('id')) ?? $formas->first();
        $f?->load('versions');
        $usos = $f ? Appointment::where('clinical_form_id', $f->id)->where('hc_status', '!=', 'Pendiente')
            ->selectRaw('form_version, count(*) as n')->groupBy('form_version')->pluck('n', 'form_version') : collect();

        return Inertia::render('Formatos', [
            'lista' => $formas->map(fn ($x) => ['id' => $x->id, 'name' => $x->name, 'specialty' => $x->specialty?->name, 'consult_type' => $x->consult_type,
                'version' => $x->currentVersion?->version, 'draft' => (bool) $x->draft, 'active' => $x->active]),
            'formato' => $f ? [
                'id' => $f->id, 'name' => $f->name, 'specialty_id' => $f->specialty_id, 'consult_type' => $f->consult_type, 'active' => $f->active,
                'version' => $f->currentVersion->version,
                'publicadas' => $f->currentVersion->sections,
                'borrador' => $f->draft,
                'versiones' => $f->versions->reverse()->values()->map(fn ($v) => ['version' => $v->version, 'date' => $v->published_at->format('Y-m-d'),
                    'notes' => $v->notes, 'campos' => $v->fieldCount(), 'usos' => (int) ($usos[$v->version] ?? 0), 'by' => $v->published_by]),
                'usos' => (int) $usos->sum(),
            ] : null,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $v = $request->validate(['name' => 'required|string|max:255', 'specialty_id' => 'nullable|exists:specialties,id',
            'consult_type' => ['required', Rule::in(Catalogo::TIPOS_CONSULTA)], 'plantilla' => 'boolean'], [], ['name' => 'nombre']);
        $general = ClinicalForm::whereNull('specialty_id')->where('consult_type', 'Primera consulta')->first();
        $secciones = ! empty($v['plantilla']) && $general ? $general->currentVersion->sections : [PlantillasFormatos::datos()];
        $f = DB::transaction(function () use ($v, $secciones, $request) {
            $f = ClinicalForm::create(['name' => $v['name'], 'specialty_id' => $v['specialty_id'] ?? null, 'consult_type' => $v['consult_type'], 'active' => true]);
            $f->versions()->create(['version' => '1.0', 'notes' => 'Versión inicial', 'sections' => $secciones, 'published_at' => now(), 'published_by' => $request->user()->name]);

            return $f;
        });
        Auditoria::log('Formato creado', $f->name);

        return response()->json(['ok' => true, 'message' => 'Formato creado. Ajusta sus secciones y campos.', 'id' => $f->id]);
    }

    public function update(Request $request, ClinicalForm $formato): JsonResponse
    {
        $v = $request->validate(['name' => 'required|string|max:255', 'specialty_id' => 'nullable|exists:specialties,id',
            'consult_type' => ['required', Rule::in(Catalogo::TIPOS_CONSULTA)], 'active' => 'boolean']);
        $formato->update($v);
        Auditoria::log('Formato modificado', "{$formato->name}: datos generales");

        return response()->json(['ok' => true, 'message' => 'Formato actualizado']);
    }

    /** Valida y normaliza la estructura de secciones/campos enviada por el constructor. */
    private function normalizar(array $secciones): array
    {
        $tipos = array_keys(Catalogo::TIPOS_CAMPO);
        $ids = [];

        return collect($secciones)->map(function ($s) use ($tipos, &$ids) {
            return [
                'title' => mb_substr(trim($s['title'] ?? 'Sección'), 0, 120) ?: 'Sección',
                'fields' => collect($s['fields'] ?? [])->map(function ($f) use ($tipos, &$ids) {
                    $id = preg_replace('/[^a-z0-9_]/i', '', (string) ($f['id'] ?? '')) ?: 'c'.substr(md5(uniqid()), 0, 6);
                    while (in_array($id, $ids, true)) {
                        $id .= '_';
                    }
                    $ids[] = $id;
                    $tipo = in_array($f['type'] ?? '', $tipos, true) ? $f['type'] : 'text';
                    $out = ['id' => $id, 'label' => mb_substr(trim($f['label'] ?? 'Pregunta'), 0, 255) ?: 'Pregunta', 'type' => $tipo,
                        'mode' => in_array($f['mode'] ?? '', ['required', 'optional', 'readonly'], true) ? $f['mode'] : 'optional',
                        'options' => in_array($tipo, Catalogo::TIPOS_CON_OPCIONES, true) ? array_values(array_filter(array_map('trim', (array) ($f['options'] ?? [])))) : []];
                    if (! empty($f['prefill'])) {
                        $out['prefill'] = $f['prefill'];
                    }

                    return $out;
                })->values()->all(),
            ];
        })->values()->all();
    }

    public function borrador(Request $request, ClinicalForm $formato): JsonResponse
    {
        $v = $request->validate(['sections' => 'present|array']);
        $formato->update(['draft' => $this->normalizar($v['sections'])]);

        return response()->json(['ok' => true]);
    }

    public function descartar(ClinicalForm $formato): JsonResponse
    {
        $formato->update(['draft' => null]);

        return response()->json(['ok' => true, 'message' => 'Cambios descartados']);
    }

    /**
     * Publica el borrador como nueva versión. Las historias ya contestadas conservan su versión;
     * las citas futuras con historia pendiente pasan a la nueva.
     */
    public function publicar(Request $request, ClinicalForm $formato): JsonResponse
    {
        $v = $request->validate(['mayor' => 'boolean', 'notes' => 'nullable|string|max:255']);
        abort_unless($formato->draft, 422, 'No hay cambios por publicar.');
        $nueva = Formatos::siguienteVersion($formato->currentVersion->version, (bool) ($v['mayor'] ?? false));
        DB::transaction(function () use ($formato, $nueva, $v, $request) {
            $formato->versions()->create(['version' => $nueva, 'notes' => $v['notes'] ?: 'Actualización del formato', 'sections' => $this->normalizar($formato->draft),
                'published_at' => now(), 'published_by' => $request->user()->name]);
            $formato->update(['draft' => null]);
            Appointment::where('clinical_form_id', $formato->id)->where('hc_status', 'Pendiente')->where('date', '>=', now()->format('Y-m-d'))
                ->update(['form_version' => $nueva]);
        });
        Auditoria::log('Formato publicado', "{$formato->name} v{$nueva}");

        return response()->json(['ok' => true, 'message' => "Versión {$nueva} publicada. Las historias pendientes usarán la nueva versión."]);
    }
}
