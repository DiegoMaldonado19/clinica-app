<?php

declare(strict_types=1);

namespace App\ClinicalRecords\Infrastructure;

use Illuminate\Encryption\Encrypter;
use RuntimeException;

/**
 * Cifrado de aplicacion de la nota SOAP (doc 05 §2.3) con una llave distinta de
 * APP_KEY, para rotarla y auditarla por separado. Falla cerrado: sin llave no
 * se escribe ni se lee contenido clinico.
 *
 * REVISION HUMANA: cifrado (CLAUDE.md). Usa el Encrypter del framework
 * (AES-256-CBC + MAC); no implementa criptografia propia.
 */
final class ClinicalCipher
{
    private ?Encrypter $encrypter = null;

    public function encrypt(?string $plain): ?string
    {
        return $plain === null || $plain === '' ? $plain : $this->encrypter()->encryptString($plain);
    }

    public function decrypt(?string $cipher): ?string
    {
        return $cipher === null || $cipher === '' ? $cipher : $this->encrypter()->decryptString($cipher);
    }

    private function encrypter(): Encrypter
    {
        if ($this->encrypter !== null) {
            return $this->encrypter;
        }

        $key = (string) config('clinic.encryption_key');

        if ($key === '') {
            throw new RuntimeException('CLINICAL_ENCRYPTION_KEY no esta configurada: no se procesa contenido clinico.');
        }

        return $this->encrypter = new Encrypter(base64_decode(str_replace('base64:', '', $key)), 'AES-256-CBC');
    }
}
