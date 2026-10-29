<?php

namespace App\Models;

use App\Models\Concerns\Trazable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientMessage extends Model
{
    use Trazable;

    protected $fillable = ['appointment_id', 'channel', 'kind', 'body', 'status', 'sent_at', 'read_at', 'error'];

    protected $casts = ['sent_at' => 'datetime', 'read_at' => 'datetime'];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
