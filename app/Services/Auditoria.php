<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AuditLog;

/** Registro de auditoría y trazabilidad de acciones. */
class Auditoria
{
    public static function log(string $accion, ?string $detalle = null, ?Appointment $cita = null, ?int $pacienteId = null, ?string $usuario = null): void
    {
        $user = auth()->user();
        AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $usuario ?? $user?->name ?? 'Sistema',
            'action' => $accion,
            'detail' => $detalle,
            'appointment_id' => $cita?->id,
            'patient_id' => $pacienteId ?? $cita?->patient_id,
            'ip' => request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
