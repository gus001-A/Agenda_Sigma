<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicalFormVersion extends Model
{
    protected $fillable = ['clinical_form_id', 'version', 'notes', 'sections', 'published_at', 'published_by'];

    protected $casts = ['sections' => 'array', 'published_at' => 'date:Y-m-d'];

    public function form(): BelongsTo
    {
        return $this->belongsTo(ClinicalForm::class, 'clinical_form_id');
    }

    public function fieldCount(): int
    {
        return collect($this->sections)->sum(fn ($s) => count($s['fields'] ?? []));
    }
}
