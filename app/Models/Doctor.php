<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Doctor extends Model
{
    protected $attributes = ['active' => true];

    protected $fillable = ['name', 'specialty_id', 'phone', 'resource_id', 'cost', 'duration', 'deposit_mode', 'deposit_value', 'days', 'start_time', 'end_time', 'notes', 'active'];

    protected $casts = ['days' => 'array', 'active' => 'boolean', 'cost' => 'float', 'deposit_value' => 'float', 'duration' => 'integer'];

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Resource::class, 'resource_id');
    }

    /** Monto del anticipo según la regla del médico (monto fijo o porcentaje del costo). */
    public function depositAmount(): float
    {
        return $this->deposit_mode === 'porcentaje'
            ? round($this->cost * $this->deposit_value / 100)
            : (float) $this->deposit_value;
    }

    public function worksOn(string $date): bool
    {
        return $this->active && in_array((int) date('w', strtotime($date)), array_map('intval', $this->days ?? []), true);
    }
}
