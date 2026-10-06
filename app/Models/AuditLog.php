<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'user_name', 'action', 'detail', 'patient_id', 'appointment_id', 'ip', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];
}
