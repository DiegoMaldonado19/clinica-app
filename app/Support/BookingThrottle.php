<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\RateLimiter;

/**
 * Doc 05 §6: 10 solicitudes por minuto por IP y 3 por hora por correo, sumadas
 * entre la landing y el asistente.
 */
final class BookingThrottle
{
    public static function tooMany(?string $ip, string $email): bool
    {
        $limits = ['booking-email:'.mb_strtolower(trim($email)) => [3, 3600]];

        if ($ip !== null) {
            $limits["booking-ip:{$ip}"] = [10, 60];
        }

        foreach ($limits as $key => [$max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                return true;
            }
        }

        foreach ($limits as $key => [, $decay]) {
            RateLimiter::hit($key, $decay);
        }

        return false;
    }
}
