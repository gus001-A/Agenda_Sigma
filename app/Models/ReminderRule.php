<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReminderRule extends Model
{
    protected $fillable = ['code', 'hours', 'active', 'text', 'only_if_hc_pending'];

    protected $casts = ['active' => 'boolean', 'only_if_hc_pending' => 'boolean', 'hours' => 'integer'];
}
