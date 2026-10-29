<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditoriaController extends Controller
{
    public function __invoke(Request $request): Response
    {
        // Filtros por columna (búsqueda bajo cada encabezado de la tabla).
        $f = collect($request->input('f', []))->only(['fecha', 'user', 'action', 'detail', 'ip'])->filter(fn ($v) => $v !== null && $v !== '');
        $porPagina = min(40, max(5, (int) $request->input('per_page', 12)));

        return Inertia::render('Auditoria', [
            'filtros' => (object) $f->all(),
            'acciones' => AuditLog::distinct()->orderBy('action')->pluck('action'),
            'registros' => AuditLog::query()
                ->when($f->get('fecha'), fn ($q, $v) => $q->whereRaw("DATE_FORMAT(created_at, '%d/%m/%Y %H:%i') like ?", ["%{$v}%"]))
                ->when($f->get('user'), fn ($q, $v) => $q->where('user_name', 'like', "%{$v}%"))
                ->when($f->get('action'), fn ($q, $v) => $q->where('action', $v))
                ->when($f->get('detail'), fn ($q, $v) => $q->where('detail', 'like', "%{$v}%"))
                ->when($f->get('ip'), fn ($q, $v) => $q->where('ip', 'like', "%{$v}%"))
                ->latest('created_at')->latest('id')->paginate($porPagina)->withQueryString()
                ->through(fn ($l) => ['id' => $l->id, 'fecha' => $l->created_at->format('d/m/Y H:i'), 'user' => $l->user_name, 'action' => $l->action,
                    'detail' => $l->detail, 'appointment_id' => $l->appointment_id, 'ip' => $l->ip]),
        ]);
    }
}
