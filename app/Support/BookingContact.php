<?php

declare(strict_types=1);

namespace App\Support;

use App\Shared\Domain\ValueObject\Nit;
use App\Shared\Domain\ValueObject\PhoneNumber;

/** Los datos de quien agenda y su aceptación de la política: iguales en la landing, el asistente y la API. */
final class BookingContact
{
    /** @return array<string, list<mixed>> */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['required', DomainRule::from(fn (string $v) => PhoneNumber::fromGuatemalan($v))],
            'nit' => ['required', DomainRule::from(fn (string $v) => Nit::from($v))],
            'accepted' => ['accepted'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'name.required' => 'Necesitamos tu nombre completo para registrar la cita.',
            'email.*' => 'Ese correo no parece válido. Revisa que tenga el formato nombre@dominio.com.',
            'phone.required' => 'Ingresa un número de 8 dígitos de Guatemala, o incluye el código de país.',
            'nit.required' => 'El NIT no pasó la verificación. Si no tienes NIT, escribe "CF".',
            'accepted.accepted' => 'Debes aceptar la política de cancelación para continuar.',
        ];
    }
}
