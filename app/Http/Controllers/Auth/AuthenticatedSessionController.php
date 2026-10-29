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
     * y solo los que existen (los crea DatabaseSeeder con contraseña demo1234).
     */
    private function accesosDemo(): array
    {
        if (! app()->isLocal()) {
            return [];
        }

        $accesos = [
            ['rol' => 'Administración', 'email' => 'cortiz@clinicasanantonio.mx', 'icono' => 'pi pi-cog', 'color' => 'var(--navy)'],
            ['rol' => 'Coordinación', 'email' => 'mfuentes@clinicasanantonio.mx', 'icono' => 'pi pi-sitemap', 'color' => 'var(--teal)'],
            ['rol' => 'Recepción', 'email' => 'atorres@clinicasanantonio.mx', 'icono' => 'pi pi-id-card', 'color' => 'var(--accent)'],
            ['rol' => 'Médico', 'email' => 'medico1@clinicasanantonio.mx', 'icono' => 'pi pi-heart', 'color' => 'var(--danger)'],
            ['rol' => 'Consulta', 'email' => 'contraloria@clinicasanantonio.mx', 'icono' => 'pi pi-eye', 'color' => 'var(--navy-500)'],
        ];
        $existentes = User::whereIn('email', array_column($accesos, 'email'))->where('active', true)->pluck('email')->all();

        return collect($accesos)->whereIn('email', $existentes)
            ->map(fn ($a) => $a + ['password' => 'demo1234'])->values()->all();
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
