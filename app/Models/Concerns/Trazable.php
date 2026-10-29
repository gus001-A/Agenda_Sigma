<?php

namespace App\Models\Concerns;

/**
 * Llena created_by / updated_by con el nombre del usuario en sesión
 * ("Sistema" cuando corre desde consola o una tarea programada).
 * Si el código ya puso un autor explícito, se respeta.
 */
trait Trazable
{
    public static function bootTrazable(): void
    {
        static::creating(function ($m) {
            $m->created_by ??= self::autorActual();
        });
        static::updating(function ($m) {
            // El token de "recordarme" cambia al iniciar o cerrar sesión; eso no es una modificación del registro.
            $cambios = array_diff(array_keys($m->getDirty()), ['remember_token', 'updated_at']);
            if ($cambios && ! $m->isDirty('updated_by')) {
                $m->updated_by = self::autorActual();
            }
        });
    }

    public static function autorActual(): string
    {
        return auth()->user()?->name ?? 'Sistema';
    }

    /** Datos de trazabilidad para enviar a las vistas. */
    public function traza(): array
    {
        return [
            'created_by' => $this->created_by, 'created_at' => $this->created_at?->toIso8601String(),
            'updated_by' => $this->updated_by, 'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
