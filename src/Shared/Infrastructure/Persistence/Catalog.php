<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Traduce el `code` estable de un catalogo a su clave foranea y de vuelta. Se
 * memoriza por instancia (singleton por peticion), nunca en estatico: las
 * pruebas siembran los catalogos en cada transaccion y los ids cambian.
 */
final class Catalog
{
    /** @var array<string, array<string, int>> */
    private array $ids = [];

    public function id(string $table, string $code): int
    {
        return $this->load($table)[$code] ?? throw new RuntimeException("{$table}.{$code} no existe en el catalogo.");
    }

    public function code(string $table, int $id): string
    {
        $code = array_search($id, $this->load($table), true);

        return $code !== false ? $code : throw new RuntimeException("{$table}#{$id} no existe en el catalogo.");
    }

    /** @return array<string, int> */
    private function load(string $table): array
    {
        return $this->ids[$table] ??= DB::table($table)->pluck('id', 'code')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }
}
