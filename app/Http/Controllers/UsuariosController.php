<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\RoleModule;
use App\Models\User;
use App\Services\Auditoria;
use App\Support\Catalogo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Usuarios, roles y matriz de acceso por módulo. */
class UsuariosController extends Controller
{
    public function index(): Response
    {
        $perm = RoleModule::all()->groupBy('role')->map(fn ($g) => $g->pluck('module')->values());

        return Inertia::render('Usuarios', [
            'usuarios' => User::with('doctor:id,name')->orderBy('role')->orderBy('name')->get(['id', 'name', 'email', 'role', 'doctor_id', 'active', 'created_by', 'created_at', 'updated_by', 'updated_at']),
            'modulos' => Catalogo::MODULOS,
            'permisos' => collect(Catalogo::ROLES)->mapWithKeys(fn ($r) => [$r => $perm[$r] ?? []]),
            'medicosSinUsuario' => Doctor::whereNotIn('id', User::whereNotNull('doctor_id')->pluck('doctor_id'))->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $v = $request->validate(['name' => 'required|string|max:255', 'email' => 'required|email|max:255|unique:users,email',
            'role' => ['required', Rule::in(Catalogo::ROLES)], 'doctor_id' => 'nullable|required_if:role,Médico|exists:doctors,id',
            'password' => 'nullable|string|min:8'], [], ['name' => 'nombre', 'email' => 'correo', 'doctor_id' => 'médico']);
        $u = User::create($v + ['password' => $v['password'] ?? Str::random(24), 'active' => true]);
        $enviado = false;
        if (empty($v['password'])) {
            // El usuario recibe un correo para definir su contraseña.
            $enviado = Password::sendResetLink(['email' => $u->email]) === Password::RESET_LINK_SENT;
        }
        Auditoria::log('Usuario creado', "{$u->name} ({$u->role})");

        return response()->json(['ok' => true, 'message' => 'Usuario creado'.($enviado ? '. Se envió el correo para definir su contraseña.' : '.')]);
    }

    public function update(Request $request, User $usuario): JsonResponse
    {
        $v = $request->validate(['name' => 'required|string|max:255', 'email' => ['required', 'email', Rule::unique('users')->ignore($usuario->id)],
            'role' => ['required', Rule::in(Catalogo::ROLES)], 'doctor_id' => 'nullable|required_if:role,Médico|exists:doctors,id',
            'active' => 'boolean', 'password' => 'nullable|string|min:8']);
        abort_if($usuario->id === $request->user()->id && (($v['active'] ?? true) === false || $v['role'] !== 'Administrador'), 422, 'No puedes desactivarte ni quitarte el rol de Administrador.');
        if (empty($v['password'])) {
            unset($v['password']);
        }
        if ($v['role'] !== 'Médico') {
            $v['doctor_id'] = null;
        }
        $usuario->update($v);
        Auditoria::log('Usuario '.($usuario->active ? 'actualizado' : 'desactivado'), "{$usuario->name} ({$usuario->role})");

        return response()->json(['ok' => true, 'message' => 'Usuario guardado']);
    }

    public function permiso(Request $request): JsonResponse
    {
        $v = $request->validate(['role' => ['required', Rule::in(Catalogo::ROLES)], 'module' => ['required', Rule::in(array_column(Catalogo::MODULOS, 'k'))], 'value' => 'required|boolean']);
        abort_if($v['role'] === 'Administrador', 422, 'El Administrador siempre tiene acceso total.');
        if ($v['value']) {
            RoleModule::firstOrCreate(['role' => $v['role'], 'module' => $v['module']]);
        } else {
            RoleModule::where('role', $v['role'])->where('module', $v['module'])->delete();
        }
        Auditoria::log('Permiso modificado', "{$v['role']}: {$v['module']} ".($v['value'] ? 'habilitado' : 'deshabilitado'));

        return response()->json(['ok' => true]);
    }
}
