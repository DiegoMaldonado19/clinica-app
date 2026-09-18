<?php

namespace App\Filament\Resources\AuditLogEntries\Pages;

use App\Filament\Resources\AuditLogEntries\AuditLogEntryResource;
use Filament\Resources\Pages\ListRecords;

class ListAuditLogEntries extends ListRecords
{
    protected static string $resource = AuditLogEntryResource::class;
}
