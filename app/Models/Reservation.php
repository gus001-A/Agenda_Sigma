<?php

namespace App\Models;

use App\Models\Concerns\Trazable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    use Trazable;

    protected $fillable = ['resource_id', 'doctor_id', 'patient_id', 'date', 'start_time', 'duration', 'procedure', 'notes', 'status', 'created_by'];

    protected $casts = ['date' => 'date:Y-m-d', 'duration' => 'integer'];

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
