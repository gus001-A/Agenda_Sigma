<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Usuario administrador para entrar al sistema.
 * La contraseña sale de ADMIN_PASSWORD en .env; si no está definida se genera
 * una al azar y se muestra una sola vez en la consola.
 *
 *   php artisan db:seed --class=AdministradorSeeder
 */
class AdministradorSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('ADMIN_PASSWORD') ?: Str::password(14, symbols: false);

        User::updateOrCreate(['email' => 'gustavo@sigma.com'], [
            'name' => 'Gustavo Vega',
            'role' => 'Administrador',
            'active' => true,
            'password' => $password,
        ]);

        $this->command?->info('Administrador listo: gustavo@sigma.com');
        if (! env('ADMIN_PASSWORD')) {
            $this->command?->warn("Contraseña generada (guárdala o define ADMIN_PASSWORD en .env): {$password}");
        }
    }
}
