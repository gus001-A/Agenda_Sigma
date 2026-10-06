<?php

namespace App\Http\Middleware;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Resource;
use App\Models\Specialty;
use App\Models\WaitlistEntry;
use App\Services\Agenda;
use App\Services\Mensajeria;
use App\Support\Catalogo;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $hoy = now()->format('Y-m-d');

        return [
            ...parent::share($request),
            'app' => ['nombre' => 'SIGMA24', 'clinica' => config('app.clinica'), 'hoy' => $hoy],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'auth' => $user ? [
                'user' => [
                    'id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role,
                    'doctor_id' => $user->doctor_id,
                    'acciones' => Catalogo::ACCIONES[$user->role] ?? [],
                    'modulos' => $user->role === 'Administrador' ? array_column(Catalogo::MODULOS, 'k') : $user->modules(),
                ],
            ] : ['user' => null],
            'menu' => fn () => $user ? collect(Catalogo::MODULOS)->filter(fn ($m) => $user->canModule($m['k']))->values() : [],
            'contadores' => fn () => $user ? [
                'espera' => WaitlistEntry::where('status', 'En espera')->count(),
                'historias' => Appointment::active()->whereBetween('date', [$hoy, Agenda::sumarDias($hoy, 3)])
                    ->whereIn('hc_status', ['Pendiente', 'Requiere actualización'])->count(),
            ] : [],
            // Catálogos ligeros para selects y formularios globales (nueva cita, bloqueos, etc.)
            'opciones' => fn () => $user ? [
                'especialidades' => Specialty::orderBy('name')->get(['id', 'name', 'active']),
                'medicos' => Doctor::orderBy('name')->get()->map(fn (Doctor $d) => [
                    'id' => $d->id, 'name' => $d->name, 'specialty_id' => $d->specialty_id, 'resource_id' => $d->resource_id,
                    'cost' => $d->cost, 'duration' => $d->duration, 'deposit' => $d->depositAmount(), 'days' => $d->days,
                    'start' => $d->start_time, 'end' => $d->end_time, 'active' => $d->active,
                ]),
                'recursos' => Resource::orderBy('id')->get(['id', 'name', 'type', 'location', 'active']),
                'canales' => array_keys(array_filter(Mensajeria::canales())),
            ] : [],
            'catalogo' => [
                'estados' => Catalogo::ESTADOS_CITA,
                'estadosManuales' => Catalogo::ESTADOS_MANUALES,
                'estadosHc' => Catalogo::ESTADOS_HC,
                'estadosAnticipo' => Catalogo::ESTADOS_ANTICIPO,
                'tiposConsulta' => Catalogo::TIPOS_CONSULTA,
                'tiposBloqueo' => Catalogo::TIPOS_BLOQUEO,
                'formasPago' => Catalogo::FORMAS_PAGO,
                'tiposRecurso' => Catalogo::TIPOS_RECURSO,
                'canales' => Catalogo::CANALES,
                'motivosCancelacion' => Catalogo::MOTIVOS_CANCELACION,
                'tiposCampo' => Catalogo::TIPOS_CAMPO,
                'tiposConOpciones' => Catalogo::TIPOS_CON_OPCIONES,
                'roles' => Catalogo::ROLES,
                'dias' => Catalogo::DIAS,
                'diasCortos' => Catalogo::DIAS_CORTOS,
                'meses' => Catalogo::MESES,
                'etiquetasMensaje' => Catalogo::ETIQUETAS_MENSAJE,
            ],
        ];
    }
}
