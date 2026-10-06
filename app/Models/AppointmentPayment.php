<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentPayment extends Model
{
    protected $fillable = ['appointment_id', 'amount', 'method', 'reference', 'user_name', 'paid_at'];

    protected $casts = ['amount' => 'float', 'paid_at' => 'datetime'];
}
