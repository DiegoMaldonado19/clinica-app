<?php

namespace App\Filament\Resources\PsychTestAssignments\Pages;

use App\Filament\Resources\PsychTestAssignments\PsychTestAssignmentResource;
use App\Models\PsychTestAssignment;
use App\Shared\Infrastructure\Audit\AuditLog;
use Filament\Resources\Pages\ViewRecord;

/** RN-12: registra en bitacora cada lectura del resultado y cada intento denegado. */
class ViewPsychTestAssignment extends ViewRecord
{
    protected static string $resource = PsychTestAssignmentResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->audit('psych_test.result_viewed');
    }

    protected function authorizeAccess(): void
    {
        if (! static::getResource()::canView($this->getRecord())) {
            $this->audit('clinical_record.access_denied');
            abort(403);
        }
    }

    private function audit(string $action): void
    {
        /** @var PsychTestAssignment $assignment */
        $assignment = $this->getRecord();

        AuditLog::record($action, 'patient', $assignment->record->patient_id, ['assignment_id' => $assignment->getKey()]);
    }
}
