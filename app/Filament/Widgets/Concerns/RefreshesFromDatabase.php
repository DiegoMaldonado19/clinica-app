<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Concerns;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * El tablero no tiene caché (ADR-032) ni sondeo: cada widget trae un ícono para
 * volver a consultar la base cuando la persona lo pide (F3-10).
 */
trait RefreshesFromDatabase
{
    protected function headingWithRefresh(string $heading): Htmlable
    {
        return new HtmlString(view('filament.widgets.refreshable-heading', ['heading' => $heading])->render());
    }
}
