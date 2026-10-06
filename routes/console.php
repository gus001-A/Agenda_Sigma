<?php

use App\Services\Mensajeria;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Recordatorios automáticos a pacientes (72 h, 24 h y día de la cita).
Artisan::command('agenda:recordatorios', function () {
    $n = Mensajeria::recordatorios();
    $this->info("{$n} recordatorios enviados");
})->purpose('Envía los recordatorios de citas pendientes');

Schedule::command('agenda:recordatorios')->hourly()->withoutOverlapping();
