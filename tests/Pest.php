<?php

declare(strict_types=1);
use Tests\TestCase;

/*
 | Unit no extiende TestCase a proposito: el dominio se prueba sin arrancar
 | Laravel ni tocar la base de datos. Si una prueba de Unit necesita el
 | framework, la frontera esta mal puesta (ADR-002).
 */

pest()->extend(TestCase::class)->in('Feature', 'Integration');
