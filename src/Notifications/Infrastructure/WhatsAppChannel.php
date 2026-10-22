<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure;

use App\Notifications\Domain\Port\NotificationChannel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Cloud API de Meta. Envía una sola plantilla de utilidad, aprobada una vez, con
 * el asunto y el aviso ya redactados como variables: no hace falta una plantilla
 * por NT. Existe solo si `WHATSAPP_TOKEN` y `WHATSAPP_PHONE_NUMBER_ID` están configurados.
 */
final class WhatsAppChannel implements NotificationChannel
{
    public static function configured(): bool
    {
        return filled(config('services.whatsapp.token')) && filled(config('services.whatsapp.phone_number_id'));
    }

    public function code(): string
    {
        return 'WHATSAPP';
    }

    public function send(string $address, string $subject, string $body): void
    {
        $config = config('services.whatsapp');

        Http::withToken($config['token'])
            ->acceptJson()
            ->timeout(15)
            ->post("https://graph.facebook.com/{$config['version']}/{$config['phone_number_id']}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => ltrim($address, '+'),
                'type' => 'template',
                'template' => [
                    'name' => $config['template'],
                    'language' => ['code' => $config['language']],
                    'components' => [[
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => self::variable($subject)],
                            ['type' => 'text', 'text' => self::variable($body)],
                        ],
                    ]],
                ],
            ])
            ->throw();
    }

    /** Meta rechaza en una variable los saltos de línea, los tabuladores y más de cuatro espacios seguidos. */
    private static function variable(string $text): string
    {
        return Str::limit(trim((string) preg_replace('/\s+/u', ' ', $text)), 900);
    }
}
