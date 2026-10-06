<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ClinicalForm extends Model
{
    protected $attributes = ['active' => true];

    protected $fillable = ['name', 'specialty_id', 'consult_type', 'active', 'draft'];

    protected $casts = ['active' => 'boolean', 'draft' => 'array'];

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ClinicalFormVersion::class)->orderBy('id');
    }

    public function currentVersion(): HasOne
    {
        return $this->hasOne(ClinicalFormVersion::class)->latestOfMany('id');
    }

    /** Devuelve la versión indicada o, si no existe, la vigente. */
    public function version(?string $v): ?ClinicalFormVersion
    {
        if ($v && $this->relationLoaded('versions')) {
            return $this->versions->firstWhere('version', $v) ?? $this->currentVersion;
        }

        return ($v ? $this->versions()->where('version', $v)->first() : null) ?? $this->currentVersion;
    }
}
