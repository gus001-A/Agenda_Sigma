<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Block extends Model
{
    protected $fillable = ['scope', 'target_id', 'type', 'date', 'start_time', 'end_time', 'recurring', 'days', 'until', 'notes', 'created_by'];

    protected $casts = ['recurring' => 'boolean', 'days' => 'array', 'date' => 'date:Y-m-d', 'until' => 'date:Y-m-d'];

    /** ¿El bloqueo aplica en la fecha indicada (Y-m-d)? */
    public function appliesOn(string $date): bool
    {
        $inicio = $this->date->format('Y-m-d');
        if (! $this->recurring) {
            return $inicio === $date;
        }
        if ($date < $inicio || ($this->until && $date > $this->until->format('Y-m-d'))) {
            return false;
        }

        return in_array((int) date('w', strtotime($date)), array_map('intval', $this->days ?? []), true);
    }

    public function targetName(): string
    {
        if ($this->scope === 'doctor') {
            return $this->target_id ? (Doctor::find($this->target_id)?->name ?? '—') : 'Todos los médicos';
        }

        return Resource::find($this->target_id)?->name ?? '—';
    }

    public function resumen(): array
    {
        return [
            'id' => $this->id, 'scope' => $this->scope, 'target_id' => $this->target_id, 'target' => $this->targetName(),
            'type' => $this->type, 'date' => $this->date->format('Y-m-d'), 'start' => $this->start_time, 'end' => $this->end_time,
            'recurring' => $this->recurring, 'days' => $this->days ?? [], 'until' => $this->until?->format('Y-m-d'),
            'notes' => $this->notes, 'created_by' => $this->created_by, 'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
