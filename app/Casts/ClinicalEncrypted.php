<?php

declare(strict_types=1);

namespace App\Casts;

use App\ClinicalRecords\Infrastructure\ClinicalCipher;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Lectura del SOAP cifrado desde Eloquent con la misma llave que el
 * repositorio. REVISION HUMANA: cifrado (CLAUDE.md).
 *
 * @implements CastsAttributes<string|null, string|null>
 */
final class ClinicalEncrypted implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return app(ClinicalCipher::class)->decrypt($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return app(ClinicalCipher::class)->encrypt($value);
    }
}
