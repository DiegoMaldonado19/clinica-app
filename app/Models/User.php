<?php

namespace App\Models;

use App\Auth\AbilityMatrix;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $role_id
 * @property bool $must_change_password
 * @property Carbon|null $temp_password_expires_at
 * @property bool $is_active
 */
#[Fillable([
    'role_id', 'name', 'email', 'phone_e164', 'password',
    'must_change_password', 'temp_password_expires_at', 'is_active',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, Notifiable;

    /** El personal entra al panel; el paciente, solo a su portal. */
    private const PANEL_ROLES = [
        'admin' => ['admin', 'secretary'],
        'portal' => ['patient'],
    ];

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** @return HasOne<Patient, $this> */
    public function patient(): HasOne
    {
        return $this->hasOne(Patient::class, 'id');
    }

    /** Unico punto donde se resuelve un permiso; `Gate` lo usa para todo lo demas. */
    public function hasAbility(string $ability): bool
    {
        return in_array($ability, app(AbilityMatrix::class)->for($this->role_id), true);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && in_array($this->role->code, self::PANEL_ROLES[$panel->getId()] ?? [], true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'temp_password_expires_at' => 'datetime',
            'last_login_at' => 'datetime',
            'must_change_password' => 'boolean',
            'two_factor_enabled' => 'boolean',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }
}
