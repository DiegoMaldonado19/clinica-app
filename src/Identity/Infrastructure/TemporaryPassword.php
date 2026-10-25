<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure;

use App\Shared\Domain\BusinessRules;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** RN-15: la contraseña que asigna otra persona siempre es temporal. */
final readonly class TemporaryPassword
{
    public function __construct(private BusinessRules $rules) {}

    public static function generate(): string
    {
        return Str::password(12, symbols: false);
    }

    /** @return array{password: string, must_change_password: true, temp_password_expires_at: Carbon} */
    public function attributes(string $password): array
    {
        return [
            'password' => Hash::make($password),
            'must_change_password' => true,
            'temp_password_expires_at' => now()->addHours($this->ttlHours()),
        ];
    }

    public function ttlHours(): int
    {
        return (int) $this->rules->value('RN-15', 'temp_password_ttl_hours');
    }
}
