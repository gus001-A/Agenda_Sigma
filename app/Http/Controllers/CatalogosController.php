<?php

namespace App\Http\Controllers;

use App\Exceptions\ReglaNegocio;
use App\Models\Appointment;
use App\Models\ClinicalForm;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Resource;
use App\Models\Specialty;
use App\Models\User;
use App\Services\Agenda;
use App\Services\Auditoria;
use App\Support\Catalogo;
use App\Support\Presentador;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Catálogos: especialidades, médicos y horarios, espacios y pacientes. */
class CatalogosController extends Controller
{
    public function index(Request $request): Response
    {
        $tab = in_array($request->tab, ['esp', 'med', 'rec', 'pac'], true) ? $request->tab : 'med';
        $data = ['tab' => $tab, 'formatos' => ClinicalForm::orderBy('name')->get(['id', 'name'])];

        if ($tab === 'esp') {
            $data['especialidades'] = Specialty::withCount('doctors')->with('firstForm:id,name', 'subForm:id,name')->orderBy('name')->get();
        } elseif ($tab === 'med') {
            $data['medicos'] = Doctor::with('specialty:id,name', 'room:id,name')->orderBy('name')->get()
                ->map(fn ($d) => $d->toArray() + ['deposit_amount' => $d->depositAmount()]);
        } elseif ($tab === 'rec') {
            $data['espacios'] = Resource::orderBy('id')->get();
        } else {
            // Filtros por columna (búsqueda bajo cada encabezado de la tabla).
            $f = collect($request->input('f', []))->only(['sigma_id', 'name', 'phone', 'email'])->filter(fn ($v) => $v !== null && $v !== '');
            $data['filtros'] = (object) $f->all();
            $porPagina = 12;
            $data['pacientes'] = Patient::query()
                ->when($f->get('sigma_id'), fn ($w, $v) => $w->where('sigma_id', 'like', "%{$v}%"))
                ->when($f->get('name'), fn ($w, $v) => $w->where('name', 'like', "%{$v}%"))
                ->when($f->get('phone'), fn ($w, $v) => $w->whereRaw("REPLACE(phone, ' ', '') like ?", ['%'.str_replace(' ', '', $v).'%']))
                ->when($f->get('email'), fn ($w, $v) => $w->where('email', 'like', "%{$v}%"))
                ->withCount('appointments')->orderBy('name')->paginate($porPagina)->withQueryString()
                ->through(function (Patient $p) {
                    $ultima = Appointment::with('specialty:id,name')->where('patient_id', $p->id)->whereIn('hc_status', ['Completado', 'Revisado'])->orderByDesc('date')->first();

                    return $p->resumen() + ['citas' => $p->appointments_count, 'ultima' => $ultima ? ['date' => $ultima->dateString(), 'specialty' => $ultima->specialty->name] : null];
                });
        }

        return Inertia::render('Catalogos', $data);
    }

    // ---------------- especialidades ----------------

    public function guardarEspecialidad(Request $request, ?Specialty $especialidad = null): JsonResponse
    {
        $v = $request->validate(['name' => 'required|string|max:255', 'description' => 'nullable|string|max:255', 'default_cost' => 'required|numeric|min:0',
            'first_form_id' => 'nullable|exists:clinical_forms,id', 'sub_form_id' => 'nullable|exists:clinical_forms,id', 'active' => 'boolean'], [], ['name' => 'nombre']);
        $nueva = ! $especialidad?->exists;
        $especialidad = $nueva ? Specialty::create($v) : tap($especialidad)->update($v);
        Auditoria::log($nueva ? 'Especialidad creada' : 'Especialidad editada', $especialidad->name);

        return response()->json(['ok' => true, 'message' => 'Especialidad guardada']);
    }

    // ---------------- médicos ----------------

    private function reglasMedico(): array
    {
        return ['name' => 'required|string|max:255', 'specialty_id' => 'required|exists:specialties,id', 'phone' => 'nullable|string|max:30',
            'resource_id' => 'nullable|exists:resources,id', 'cost' => 'required|numeric|min:0', 'duration' => 'required|integer|in:15,20,30,40,45,50,60',
            'deposit_mode' => ['required', Rule::in(['monto', 'porcentaje'])], 'deposit_value' => 'required|numeric|min:0',
            'days' => 'required|array|min:1', 'days.*' => 'integer|between:0,6', 'start_time' => 'required|date_format:H:i', 'end_time' => 'required|date_format:H:i',
            'notes' => 'nullable|string|max:255', 'active' => 'boolean', 'email' => 'nullable|email|max:255'];
    }

    /** Vista previa de la agenda que generaría la configuración (y citas que quedarían fuera). */
    public function vistaPreviaMedico(Request $request): JsonResponse
    {
        $v = $request->validate(['id' => 'nullable|exists:doctors,id', 'days' => 'array', 'start_time' => 'required|date_format:H:i', 'end_time' => 'required|date_format:H:i',
            'duration' => 'required|integer|min:5', 'cost' => 'nullable|numeric', 'deposit_mode' => 'nullable', 'deposit_value' => 'nullable|numeric']);
        $dias = array_map('intval', $v['days'] ?? []);
        $a = Agenda::min($v['start_time']);
        $b = Agenda::min($v['end_time']);
        if (! $dias || $b <= $a) {
            return response()->json(['valido' => false]);
        }
        $tmp = new Doctor(['days' => $dias, 'start_time' => $v['start_time'], 'end_time' => $v['end_time'], 'duration' => $v['duration'], 'active' => true,
            'cost' => $v['cost'] ?? 0, 'deposit_mode' => $v['deposit_mode'] ?? 'monto', 'deposit_value' => $v['deposit_value'] ?? 0]);
        $tmp->id = $v['id'] ?? 0;
        $hoy = now()->format('Y-m-d');
        $sig = $hoy;
        for ($i = 0; $i < 14 && ! in_array(Agenda::diaSemana($sig), $dias, true); $i++) {
            $sig = Agenda::sumarDias($sig, 1);
        }
        $esp = collect((new Agenda($sig, $sig))->espacios($tmp, $sig))->where('off', false)->map(fn ($s) => ['time' => $s['time'], 'state' => $s['state']])->values();
        $fuera = 0;
        if (! empty($v['id'])) {
            $fuera = Appointment::active()->where('doctor_id', $v['id'])->where('date', '>=', $hoy)->get()->filter(function ($x) use ($dias, $a, $b, $v) {
                $m = Agenda::min($x->time);

                return ! in_array(Agenda::diaSemana($x->dateString()), $dias, true) || $m < $a || $m + (int) $v['duration'] > $b || ($m - $a) % (int) $v['duration'] !== 0;
            })->count();
        }
        $n = intdiv($b - $a, (int) $v['duration']);

        return response()->json(['valido' => true, 'porDia' => $n, 'porSemana' => $n * count($dias), 'anticipo' => $tmp->depositAmount(),
            'siguiente' => $sig, 'espacios' => $esp, 'fuera' => $fuera]);
    }

    public function guardarMedico(Request $request, ?Doctor $medico = null): JsonResponse
    {
        $v = $request->validate($this->reglasMedico(), [], ['name' => 'nombre', 'days' => 'días de atención']);
        if (Agenda::min($v['end_time']) <= Agenda::min($v['start_time'])) {
            throw new ReglaNegocio('La hora final debe ser posterior a la inicial.');
        }
        $email = $v['email'] ?? null;
        unset($v['email']);
        $v['days'] = array_values(array_map('intval', $v['days']));
        if ($medico?->exists) {
            $medico->update($v);
            Auditoria::log('Horario de médico actualizado', "{$medico->name}: ".implode(', ', array_map(fn ($x) => Catalogo::DIAS_CORTOS[$x], $v['days']))." {$v['start_time']} a {$v['end_time']}, {$v['duration']} min");
        } else {
            $medico = Doctor::create($v);
            // Cada médico tiene un usuario para consultar su agenda y preconsulta.
            User::create(['name' => $medico->name, 'email' => $email ?: 'medico'.$medico->id.'.'.Str::lower(Str::random(4)).'@clinicasanantonio.mx',
                'role' => 'Médico', 'doctor_id' => $medico->id, 'password' => Hash::make(Str::random(16))]);
            Auditoria::log('Médico creado', $medico->name);
        }

        return response()->json(['ok' => true, 'message' => 'Configuración guardada. La agenda se regeneró.']);
    }

    // ---------------- espacios ----------------

    public function guardarEspacio(Request $request, ?Resource $espacio = null): JsonResponse
    {
        $v = $request->validate(['name' => 'required|string|max:255', 'type' => ['required', Rule::in(Catalogo::TIPOS_RECURSO)], 'location' => 'nullable|string|max:255',
            'capacity' => 'required|integer|min:1', 'opens_at' => 'required|date_format:H:i', 'closes_at' => 'required|date_format:H:i', 'cost' => 'required|numeric|min:0',
            'equipment' => 'nullable|string|max:255', 'responsible' => 'nullable|string|max:255', 'rules' => 'nullable|string|max:255', 'active' => 'boolean'], [], ['name' => 'nombre']);
        $nuevo = ! $espacio?->exists;
        $espacio = $nuevo ? Resource::create($v) : tap($espacio)->update($v);
        Auditoria::log($nuevo ? 'Espacio creado' : 'Espacio editado', $espacio->name);

        return response()->json(['ok' => true, 'message' => 'Espacio guardado']);
    }

    // ---------------- pacientes ----------------

    public function guardarPaciente(Request $request, ?Patient $paciente = null): JsonResponse
    {
        $v = $request->validate(['name' => 'required|string|max:255', 'birth_date' => 'required|date|before_or_equal:today', 'phone' => 'required|string|max:30',
            'email' => 'nullable|email|max:255'], [], ['name' => 'nombre completo', 'birth_date' => 'fecha de nacimiento', 'phone' => 'teléfono']);
        if ($paciente?->exists) {
            $paciente->update($v);
            Auditoria::log('Paciente editado', "{$paciente->name} ({$paciente->sigma_id})", pacienteId: $paciente->id);

            return response()->json(['ok' => true, 'message' => 'Paciente guardado', 'patient' => $paciente->resumen()]);
        }
        // Alta controlada: se evita duplicar expedientes con el mismo nombre y fecha de nacimiento.
        $dup = Patient::whereRaw('LOWER(name) = ?', [mb_strtolower($v['name'])])->whereDate('birth_date', $v['birth_date'])->first();
        if ($dup) {
            return response()->json(['ok' => false, 'duplicado' => true, 'message' => "Ya existe {$dup->name} ({$dup->sigma_id}). Se seleccionó el expediente existente.",
                'patient' => $dup->resumen() + ['previas' => 0]], 409);
        }
        $p = Patient::create($v + ['sigma_id' => Patient::nextSigmaId()]);
        Auditoria::log('Alta de paciente', "{$p->name} ({$p->sigma_id})", pacienteId: $p->id);

        return response()->json(['ok' => true, 'message' => "Paciente dado de alta con ID {$p->sigma_id}", 'patient' => $p->resumen() + ['previas' => 0]]);
    }

    /** Historial de citas de un paciente (expediente resumido). */
    public function historialPaciente(Patient $paciente): JsonResponse
    {
        return response()->json([
            'patient' => $paciente->resumen(),
            'citas' => Appointment::with('doctor:id,name', 'specialty:id,name', 'patient:id,name,sigma_id')->where('patient_id', $paciente->id)
                ->orderByDesc('date')->orderByDesc('time')->limit(50)->get()->map(fn ($a) => Presentador::cita($a)),
        ]);
    }
}
