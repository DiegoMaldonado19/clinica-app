<?php

namespace App\Filament\Resources\PsychTestAssignments\Pages;

use App\ClinicalRecords\Application\PsychTestService;
use App\ClinicalRecords\Domain\PsychTest;
use App\Filament\Resources\PsychTestAssignments\PsychTestAssignmentResource;
use App\Filament\Support\DomainCommand;
use App\Models\Patient;
use App\Models\User;
use App\Shared\Infrastructure\Audit\AuditLog;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Carbon;

class ListPsychTestAssignments extends ListRecords
{
    protected static string $resource = PsychTestAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('assign')
                ->label('Asignar prueba')
                ->visible(DomainCommand::userCan('psych_test.assign'))
                ->schema([
                    // Solo usuarios con ficha en `patients`: el expediente clinico cuelga de ella.
                    Select::make('patient_id')->label('Paciente')->searchable()->required()
                        ->options(fn (): array => User::query()->whereIn('id', Patient::query()->select('id'))->orderBy('name')->pluck('name', 'id')->all()),
                    Select::make('test')->label('Prueba')->required()
                        ->options(array_combine(
                            array_column(PsychTest::cases(), 'value'),
                            array_map(fn (PsychTest $test): string => $test->label(), PsychTest::cases()),
                        )),
                    DatePicker::make('due_on')->label('Responder hasta')->required()
                        ->default(now(config('clinic.timezone'))->addDays(7))
                        ->minDate(now(config('clinic.timezone'))->addDay()->startOfDay()),
                ])
                ->action(fn (array $data) => DomainCommand::run(function () use ($data): void {
                    $id = app(PsychTestService::class)->assign(
                        $data['patient_id'],
                        PsychTest::from($data['test']),
                        Carbon::parse($data['due_on'], config('clinic.timezone'))->endOfDay()->utc()->toDateTimeImmutable(),
                        (string) auth()->id(),
                    );
                    AuditLog::record('psych_test.assigned', 'patient', $data['patient_id'], ['assignment_id' => $id, 'test' => $data['test']]);
                }, 'Prueba asignada. Le avisamos al paciente por correo.')),
        ];
    }
}
