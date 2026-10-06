<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentFile extends Model
{
    protected $fillable = ['appointment_id', 'patient_id', 'field_id', 'original_name', 'path', 'mime', 'size_kb'];

    protected $casts = ['size_kb' => 'integer'];

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    public function resumen(): array
    {
        return [
            'id' => $this->id, 'field_id' => $this->field_id, 'name' => $this->original_name,
            'size' => $this->size_kb, 'mime' => $this->mime, 'image' => $this->isImage(),
            'has_file' => (bool) $this->path, 'at' => $this->created_at?->toIso8601String(),
        ];
    }
}
