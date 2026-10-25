<?php

namespace App\Models;

use App\Auth\AbilityMatrix;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\Email\Contracts\HasEmailAuthentication;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
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
use Illuminate\Support\Facades\Storage;

/**
 * @property int $role_id
 * @property string|null $avatar_path
 * @property bool $must_change_password
 * @property Carbon|null $temp_password_expires_at
 * @property bool $is_active
 * @property bool $two_factor_enabled
 */
#[Fillable([
    'role_id', 'name', 'email', 'phone_e164', 'avatar_path', 'password',
    'must_change_password', 'temp_password_expires_at', 'is_active',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasAvatar, HasEmailAuthentication
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery, Notifiable;

    protected static function booted(): void
    {
        // La foto reemplazada no queda en el bucket (ADR-041).
        static::updated(function (User $user): void {
            $previous = (string) $user->getOriginal('avatar_path');

            if ($user->wasChanged('avatar_path') && str_starts_with($previous, 'avatars/')) {
                Storage::disk('s3')->delete($previous);
            }
        });
    }

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

    /** El paciente entra a su portal; cualquier otro rol, al panel del personal (ADR-040). */
    public function isStaff(): bool
    {
        return $this->role->code !== 'patient';
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $panel->getId() === $this->panelId();
    }

    public function homePanel(): Panel
    {
        return Filament::getPanel($this->panelId());
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar_path ? route('avatar', ['user' => $this, 'v' => hash('crc32b', $this->avatar_path)]) : null;
    }

    /** El codigo por correo de la MFA de Filament, sobre la columna que ya existia. */
    public function hasEmailAuthentication(): bool
    {
        return $this->two_factor_enabled;
    }

    public function toggleEmailAuthentication(bool $condition): void
    {
        $this->forceFill(['two_factor_enabled' => $condition])->save();
    }

    private function panelId(): string
    {
        return $this->isStaff() ? 'admin' : 'portal';
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
