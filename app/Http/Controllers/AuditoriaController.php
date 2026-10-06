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
        $q = trim((string) $request->q);

        return Inertia::render('Auditoria', [
            'q' => $q,
            'registros' => AuditLog::query()
                ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('user_name', 'like', "%{$q}%")->orWhere('action', 'like', "%{$q}%")->orWhere('detail', 'like', "%{$q}%")))
                ->latest('created_at')->latest('id')->paginate(50)->withQueryString()
                ->through(fn ($l) => ['id' => $l->id, 'at' => $l->created_at->toIso8601String(), 'user' => $l->user_name, 'action' => $l->action,
                    'detail' => $l->detail, 'appointment_id' => $l->appointment_id, 'ip' => $l->ip]),
        ]);
    }
}
