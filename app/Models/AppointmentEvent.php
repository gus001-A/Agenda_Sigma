<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['appointment_id', 'user_name', 'text', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];
}
