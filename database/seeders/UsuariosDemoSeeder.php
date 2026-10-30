<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsuariosDemoSeeder extends Seeder
{
    public function run(): void
    {
        $usuarios = [
            [
                'name'     => 'Administrador',
                'email'    => 'admin@clinicasanantonio.mx',
                'password' => 'Admin123!',
                'role'     => 'Administrador',
            ],
            [
                'name'     => 'Doctor Demo',
                'email'    => 'doctor@clinicasanantonio.mx',
                'password' => 'Doctor123!',
                'role'     => 'Médico',
            ],
            [
                'name'     => 'Paciente Demo',
                'email'    => 'paciente@clinicasanantonio.mx',
                'password' => 'Paciente123!',
                'role'     => 'Paciente',
            ],
            [
                'name'     => 'Recepcionista Demo',
                'email'    => 'recepcion@clinicasanantonio.mx',
                'password' => 'Recepcion123!',
                'role'     => 'Recepción',
            ],
        ];

        foreach ($usuarios as $u) {
            DB::table('users')->updateOrInsert(
                ['email' => $u['email']],
                [
                    'name'              => $u['name'],
                    'password'          => Hash::make($u['password']),
                    'role'              => $u['role'],
                    'active'            => 1,
                    'email_verified_at' => now(),
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]
            );
        }
    }
}