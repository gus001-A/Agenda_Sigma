<?php

namespace App\Models;

use App\Support\Catalogo;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $attributes = ['active' => true, 'role' => 'Recepción'];

    protected $fillable = ['name', 'email', 'password', 'role', 'doctor_id', 'active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function isDoctor(): bool
    {
        return $this->role === 'Médico';
    }

    /** Permiso de acción (crear citas, bloquear, ver contenido clínico, etc.). */
    public function can_(string $accion): bool
    {
        return in_array($accion, Catalogo::ACCIONES[$this->role] ?? [], true);
    }

    /** Acceso a un módulo según la matriz de permisos configurable. */
    public function modules(): array
    {
        static $cache = [];

        return $cache[$this->role] ??= RoleModule::where('role', $this->role)->pluck('module')->all();
    }

    public function canModule(string $modulo): bool
    {
        return $this->role === 'Administrador' || in_array($modulo, $this->modules(), true);
    }
}
