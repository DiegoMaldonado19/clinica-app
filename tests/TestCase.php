<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    /**
     * Cada prueba arranca con la cache vacia. En local el almacen es el Redis de
     * desarrollo, y sin esto el contador del limitador de intentos sobrevive
     * entre ejecuciones: la suite empieza a fallar por lo que quedo de la vez
     * anterior, no por el codigo.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        // Llave SOAP efimera: ninguna llave de cifrado real vive en el repositorio.
        config(['clinic.encryption_key' => 'base64:'.base64_encode(random_bytes(32))]);
    }
}
