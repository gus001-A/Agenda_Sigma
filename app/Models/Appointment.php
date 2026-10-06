<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appointment extends Model
{
    protected $fillable = [
        'token', 'patient_id', 'doctor_id', 'specialty_id', 'date', 'time', 'duration', 'type', 'reason', 'cost', 'deposit',
        'paid', 'refunded', 'payment_method', 'notes', 'channel', 'confirmed', 'status', 'clinical_form_id', 'form_version',
        'hc_status', 'hc_data', 'hc_submitted_at', 'reviewed_by', 'attention_note', 'update_request', 'prev_asked',
        'rescheduled_from_id', 'rescheduled_to_id', 'reminders_sent', 'created_by',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d', 'cost' => 'float', 'deposit' => 'float', 'paid' => 'float', 'refunded' => 'boolean',
        'confirmed' => 'boolean', 'hc_data' => 'array', 'hc_submitted_at' => 'datetime', 'prev_asked' => 'boolean',
        'reminders_sent' => 'array', 'duration' => 'integer',
    ];

    /** Estados que liberan el horario (la cita deja de ocupar agenda). */
    public const INACTIVOS = ['Cancelada', 'Reagendada'];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(ClinicalForm::class, 'clinical_form_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(AppointmentPayment::class)->orderBy('paid_at');
    }

    public function events(): HasMany
    {
        return $this->hasMany(AppointmentEvent::class)->orderBy('created_at')->orderBy('id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(AppointmentFile::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(PatientMessage::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNotIn('status', self::INACTIVOS);
    }

    public function isActive(): bool
    {
        return ! in_array($this->status, self::INACTIVOS, true);
    }

    public function dateString(): string
    {
        return $this->date->format('Y-m-d');
    }

    public function depositState(): string
    {
        if (! ($this->deposit > 0)) {
            return 'No requerido';
        }
        if ($this->refunded) {
            return 'Reembolsado';
        }
        if ($this->paid >= $this->deposit) {
            return 'Pagado';
        }

        return $this->paid > 0 ? 'Parcial' : 'Pendiente';
    }

    public function depositBalance(): float
    {
        return max(0, $this->deposit - $this->paid);
    }

    public function portalUrl(): string
    {
        return route('portal.show', $this->token);
    }

    /** Agrega un evento al historial de la cita. */
    public function registrar(string $texto, ?string $usuario = null): void
    {
        $this->events()->create(['text' => mb_substr($texto, 0, 500), 'user_name' => $usuario ?? auth()->user()?->name ?? 'Sistema', 'created_at' => now()]);
    }
}
