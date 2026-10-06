<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Specialty extends Model
{
    protected $attributes = ['active' => true];

    protected $fillable = ['name', 'description', 'default_cost', 'first_form_id', 'sub_form_id', 'active'];

    protected $casts = ['active' => 'boolean', 'default_cost' => 'float'];

    public function doctors(): HasMany
    {
        return $this->hasMany(Doctor::class);
    }

    public function firstForm(): BelongsTo
    {
        return $this->belongsTo(ClinicalForm::class, 'first_form_id');
    }

    public function subForm(): BelongsTo
    {
        return $this->belongsTo(ClinicalForm::class, 'sub_form_id');
    }
}
