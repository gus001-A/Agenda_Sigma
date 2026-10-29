<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\User;
use App\Services\Formatos;

/** Serialización de modelos para el frontend. */
class Presentador
{
    /** Versión compacta de la cita para tablas, agenda y listas. */
    public static function cita(Appointment $a): array
    {
        return [
            'id' => $a->id,
            'date' => $a->dateString(),
            'time' => $a->time,
            'duration' => $a->duration,
            'patient_id' => $a->patient_id,
            'patient' => $a->patient?->name ?? 'Sin paciente',
            'sigma_id' => $a->patient?->sigma_id,
            'doctor_id' => $a->doctor_id,
            'doctor' => $a->doctor?->name,
            'specialty_id' => $a->specialty_id,
            'specialty' => $a->specialty?->name,
            'type' => $a->type,
            'reason' => $a->reason,
            'status' => $a->status,
            'confirmed' => $a->confirmed,
            'hc_status' => $a->hc_status,
            'cost' => $a->cost,
            'deposit' => $a->deposit,
            'paid' => $a->paid,
            'refunded' => $a->refunded,
            'deposit_state' => $a->depositState(),
            'balance' => $a->refunded ? 0 : $a->depositBalance(),
            'active' => $a->isActive(),
        ] + $a->traza();
    }

    /** Detalle completo para el panel lateral de la cita (incluye acciones permitidas). */
    public static function detalle(Appointment $a, User $u): array
    {
        $a->loadMissing('patient', 'doctor.room', 'specialty', 'form', 'payments', 'events', 'files');
        $activa = $a->isActive();
        $hoy = now()->format('Y-m-d');
        $puede = fn ($x) => $u->can_($x);
        $acc = [
            'confirmar' => $activa && $puede('cita') && in_array($a->status, ['Agendada', 'Anticipo pendiente'], true) && ! $a->confirmed,
            'pagar' => $activa && $puede('anticipo') && $a->deposit > $a->paid,
            'iniciar' => $activa && $puede('consulta') && in_array($a->status, ['Agendada', 'Confirmada', 'Anticipo pendiente'], true) && $a->dateString() === $hoy,
            'finalizar' => $a->status === 'En consulta' && $puede('consulta'),
            'reagendar' => $activa && $puede('cita') && ! in_array($a->status, ['Atendida', 'En consulta'], true),
            'cancelar' => $activa && $puede('cita') && ! in_array($a->status, ['Atendida', 'En consulta'], true),
            'reembolsar' => ! $activa && $a->paid > 0 && ! $a->refunded && $puede('anticipo'),
            'verHistoria' => $puede('verclinico') && (! $u->isDoctor() || $u->doctor_id === $a->doctor_id),
            'reenviar' => $activa && $a->status !== 'Atendida' && $puede('cita'),
            'portal' => $activa && $a->status !== 'Atendida',
            'editar' => $activa && $puede('cita'),
            'estado' => $activa && $puede('cita'),
        ];
        $r = $a->doctor->room;

        return self::cita($a) + [
            'patient_info' => $a->patient->resumen(),
            'room' => $r ? ['name' => $r->name, 'location' => $r->location] : null,
            'notes' => $a->notes,
            'channel' => $a->channel,
            'payment_method' => $a->payment_method,
            'form' => $a->form ? ['id' => $a->form->id, 'name' => $a->form->name, 'version' => $a->form_version] : null,
            'hc_submitted_at' => $a->hc_submitted_at?->toIso8601String(),
            'reviewed_by' => $a->reviewed_by,
            'progress' => Formatos::avance($a),
            'files_count' => $a->files->count(),
            'attention_note' => $a->attention_note,
            'update_request' => $a->update_request,
            'portal_url' => $a->portalUrl(),
            'payments' => $a->payments->map(fn ($p) => ['amount' => $p->amount, 'method' => $p->method, 'reference' => $p->reference, 'at' => $p->paid_at->toIso8601String(), 'user' => $p->user_name]),
            'history' => $a->events->sortByDesc('created_at')->values()->map(fn ($e) => ['text' => $e->text, 'user' => $e->user_name, 'at' => $e->created_at->toIso8601String()]),
            'rescheduled_to_id' => $a->rescheduled_to_id,
            'acciones' => $acc,
        ];
    }

    /** Espacio de agenda (slot) para la vista de calendario y recepción. */
    public static function espacio(array $s): array
    {
        return [
            'time' => $s['time'], 'm' => $s['m'], 'dur' => $s['dur'], 'state' => $s['state'], 'off' => $s['off'],
            'appt' => $s['appt'] ? [
                'id' => $s['appt']->id, 'patient' => $s['appt']->patient?->name, 'status' => $s['appt']->status,
                'hc_status' => $s['appt']->hc_status, 'type' => $s['appt']->type,
                'debt' => $s['appt']->refunded ? 0 : $s['appt']->depositBalance(), 'deposit_state' => $s['appt']->depositState(),
            ] : null,
            'block' => $s['block'] ? ['id' => $s['block']->id, 'type' => $s['block']->type] : null,
        ];
    }
}
