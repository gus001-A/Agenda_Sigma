<?php

namespace App\Http\Controllers;

use App\Models\PatientMessage;
use App\Models\ReminderRule;
use App\Models\Setting;
use App\Services\Auditoria;
use App\Services\Mensajeria;
use App\Support\Catalogo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificacionesController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Notificaciones', [
            'total' => PatientMessage::count(),
            'mensajes' => PatientMessage::with('appointment:id,patient_id,status', 'appointment.patient:id,name')->latest('sent_at')->latest('id')->limit(60)->get()
                ->map(fn ($m) => ['id' => $m->id, 'appointment_id' => $m->appointment_id, 'patient' => $m->appointment?->patient?->name,
                    'active' => $m->appointment && ! in_array($m->appointment->status, ['Cancelada', 'Reagendada', 'Atendida'], true),
                    'channel' => $m->channel, 'kind' => Catalogo::ETIQUETAS_MENSAJE[$m->kind] ?? $m->kind, 'body' => $m->body,
                    'status' => $m->status, 'error' => $m->error, 'at' => $m->sent_at->toIso8601String()]),
            'recordatorios' => ReminderRule::orderByDesc('hours')->get(),
            'canales' => Mensajeria::canales(),
        ]);
    }

    public function ejecutar(): JsonResponse
    {
        $n = Mensajeria::recordatorios();
        Auditoria::log('Recordatorios ejecutados', "{$n} mensajes");

        return response()->json(['ok' => true, 'message' => $n ? "{$n} recordatorios enviados" : 'No hay recordatorios pendientes por enviar en este momento']);
    }

    public function guardarRecordatorio(Request $request, ReminderRule $regla): JsonResponse
    {
        $v = $request->validate(['active' => 'boolean', 'text' => 'required|string|max:500', 'only_if_hc_pending' => 'boolean']);
        $regla->update($v);
        Auditoria::log('Recordatorio configurado', "{$regla->hours} h: ".($regla->active ? 'activo' : 'inactivo'));

        return response()->json(['ok' => true, 'message' => 'Recordatorio guardado']);
    }

    public function guardarCanales(Request $request): JsonResponse
    {
        $v = $request->validate(['canales' => 'required|array']);
        $canales = collect(Catalogo::CANALES)->mapWithKeys(fn ($c) => [$c => (bool) ($v['canales'][$c] ?? false)])->all();
        Setting::put('canales', $canales);
        Auditoria::log('Canales de notificación', collect($canales)->map(fn ($on, $c) => $c.($on ? ' sí' : ' no'))->implode(', '));

        return response()->json(['ok' => true, 'message' => 'Canales actualizados']);
    }
}
