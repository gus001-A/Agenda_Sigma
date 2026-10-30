<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'accesosDemo' => $this->accesosDemo(),
        ]);
    }

    /**
     * Usuarios de demostración para entrar con un clic. Solo en entorno local
     * y solo los que existen y están activos.
     *
     * IMPORTANTE: el email y la contraseña deben coincidir con el seeder.
     */
    private function accesosDemo(): array
    {
        if (! app()->isLocal()) {
            return [];
        }

        // Cada acceso incluye email y password reales (coinciden con UsuariosDemoSeeder)
        $accesos = [
            [
                'rol'      => 'Administración',
                'email'    => 'admin@clinicasanantonio.mx',
                'password' => 'Admin123!',
                'icono'    => 'pi pi-cog',
                'color'    => 'var(--navy)',
            ],
            [
                'rol'      => 'Médico',
                'email'    => 'doctor@clinicasanantonio.mx',
                'password' => 'Doctor123!',
                'icono'    => 'pi pi-heart',
                'color'    => 'var(--danger)',
            ],
            [
                'rol'      => 'Paciente',
                'email'    => 'paciente@clinicasanantonio.mx',
                'password' => 'Paciente123!',
                'icono'    => 'pi pi-user',
                'color'    => 'var(--teal)',
            ],
            [
                'rol'      => 'Recepción',
                'email'    => 'recepcion@clinicasanantonio.mx',
                'password' => 'Recepcion123!',
                'icono'    => 'pi pi-id-card',
                'color'    => 'var(--accent)',
            ],
        ];

        // Solo mostrar los que realmente existen y están activos
        $existentes = User::whereIn('email', array_column($accesos, 'email'))
            ->where('active', true)
            ->pluck('email')
            ->all();

        return collect($accesos)
            ->whereIn('email', $existentes)
            ->values()
            ->all();
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('inicio', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}