<?php

namespace App\Models;

use App\Models\Concerns\Trazable;
use Illuminate\Database\Eloquent\Model;

class Resource extends Model
{
    use Trazable;

    protected $attributes = ['active' => true];

    protected $fillable = ['name', 'type', 'location', 'capacity', 'opens_at', 'closes_at', 'cost', 'equipment', 'responsible', 'rules', 'active'];

    protected $casts = ['active' => 'boolean', 'cost' => 'float', 'capacity' => 'integer'];
}
