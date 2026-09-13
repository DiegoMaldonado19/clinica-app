<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role_id' => fn (): int => $this->roleId('patient'),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'is_active' => true,
        ];
    }

    public function role(string $code): static
    {
        return $this->state(fn (): array => ['role_id' => $this->roleId($code)]);
    }

    /** RN-15: credencial temporal con TTL y cambio obligatorio. */
    public function withTemporaryPassword(string $expiresAt = '+72 hours'): static
    {
        return $this->state(fn (): array => [
            'must_change_password' => true,
            'temp_password_expires_at' => Carbon::parse($expiresAt),
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    private function roleId(string $code): int
    {
        return (int) DB::table('roles')->where('code', $code)->value('id');
    }
}
