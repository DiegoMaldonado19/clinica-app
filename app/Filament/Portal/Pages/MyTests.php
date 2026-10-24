<?php

declare(strict_types=1);

namespace App\Filament\Portal\Pages;

use App\ClinicalRecords\Application\PsychTestService;
use App\ClinicalRecords\Domain\PsychTest;
use App\Filament\Resources\PsychTestAssignments\PsychTestAssignmentResource;
use App\Filament\Support\DomainCommand;
use App\Models\PsychTestAssignment;
use App\Shared\Infrastructure\Audit\AuditLog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * MU-06 · HU-13. El paciente responde; la puntuacion es de la psicologa y aqui
 * no se muestra. Todo se limita al paciente en sesion (RN-13).
 */
class MyTests extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Mis pruebas';

    protected static ?string $title = 'Mis pruebas';

    protected static ?string $slug = 'mis-pruebas';

    protected string $view = 'filament.portal.my-tests';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasAbility('psych_test.answer');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->mine())
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('test')->label('Prueba')->formatStateUsing(fn (PsychTest $state): string => $state->label()),
                TextColumn::make('due_at')->label('Responder hasta')->date('l j \d\e F'),
                PsychTestAssignmentResource::statusColumn(),
            ])
            ->recordActions([
                Action::make('answer')
                    ->label('Responder')
                    ->visible(fn (PsychTestAssignment $record): bool => $record->statusCode() === 'PENDIENTE')
                    ->slideOver()
                    ->modalHeading(fn (PsychTestAssignment $record): string => $record->test->label())
                    ->modalDescription(PsychTest::QUESTION)
                    ->modalSubmitActionLabel('Enviar respuestas')
                    ->schema(fn (PsychTestAssignment $record): array => array_map(
                        fn (int $i, string $item): Radio => Radio::make("answers.{$i}")->label(($i + 1).'. '.$item)->options(PsychTest::OPTIONS)->required(),
                        array_keys($record->test->items()),
                        $record->test->items(),
                    ))
                    ->action(fn (PsychTestAssignment $record, array $data) => $this->submit($record, $data['answers'])),
            ])
            ->emptyStateHeading('No tienes pruebas por responder')
            ->emptyStateDescription('Si tu psicóloga te asigna una, te avisaremos por correo.');
    }

    /** @param array<int, int|string> $answers */
    private function submit(PsychTestAssignment $record, array $answers): void
    {
        $id = (string) $this->mine()->whereKey($record->getKey())->firstOrFail()->getKey();
        ksort($answers);
        $urgent = false;

        DomainCommand::run(function () use ($id, $answers, &$urgent): void {
            $urgent = app(PsychTestService::class)->submit($id, array_map('intval', array_values($answers)))->urgent;
            AuditLog::record('psych_test.submitted', 'patient', (string) auth()->id(), ['assignment_id' => $id]);
        }, 'Gracias. Tu psicóloga revisará tus respuestas contigo.');

        if ($urgent) {
            Notification::make()->warning()->persistent()
                ->title('Si estás pensando en hacerte daño, no esperes: llama ahora a la línea de crisis '.config('clinic.crisis_line').'.')
                ->body('Ya avisamos a tu psicóloga.')
                ->send();
        }
    }

    /** @return Builder<PsychTestAssignment> */
    private function mine(): Builder
    {
        return PsychTestAssignment::query()->whereRelation('record', 'patient_id', auth()->id());
    }
}
