<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    protected $fillable = ['sigma_id', 'legacy_id', 'name', 'curp', 'sex', 'birth_date', 'phone', 'email'];

    protected $casts = ['birth_date' => 'date:Y-m-d'];

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function age(): ?int
    {
        return $this->birth_date?->age;
    }

    /** Siguiente identificador SIGMA24 disponible (SG-#####), continuando la numeración de SIGMA. */
    public static function nextSigmaId(): string
    {
        $max = static::query()->where('sigma_id', 'like', 'SG-%')->selectRaw('MAX(CAST(SUBSTRING(sigma_id, 4) AS UNSIGNED)) as m')->value('m') ?? 10223;

        return 'SG-'.($max + 1);
    }

    public function resumen(): array
    {
        return [
            'id' => $this->id, 'sigma_id' => $this->sigma_id, 'name' => $this->name,
            'birth_date' => $this->birth_date?->format('Y-m-d'), 'age' => $this->age(),
            'phone' => $this->phone, 'email' => $this->email, 'curp' => $this->curp, 'sex' => $this->sex,
        ];
    }
}
