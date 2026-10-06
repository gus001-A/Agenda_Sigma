<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoleModule extends Model
{
    public $timestamps = false;

    protected $fillable = ['role', 'module'];
}
