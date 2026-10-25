<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Shared\Infrastructure\Audit\AuditLog;
use App\Support\BusinessRuleSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * MU-15 · HU-17. Cambiar un plazo o una politica no requiere desplegar. Nunca
 * se edita la fila vigente: se inserta una nueva con fecha de vigencia, de modo
 * que el historial queda y las citas existentes no cambian (CA-14).
 */
class BusinessRules extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'Plazos y políticas';

    protected static ?string $title = 'Plazos y políticas';

    protected string $view = 'filament.pages.business-rules';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasAbility('settings.manage');
    }

    /** @return list<object{rule_code: string, param_key: string, param_value: string, value_type: string, effective_from: string}> */
    public function current(): array
    {
        $latest = DB::table('business_rule_settings')
            ->where('effective_from', '<=', now())
            ->selectRaw('rule_code, param_key, max(effective_from) as effective_from')
            ->groupBy('rule_code', 'param_key');

        return DB::table('business_rule_settings as s')
            ->joinSub($latest, 'l', fn ($join) => $join->on('s.rule_code', '=', 'l.rule_code')->on('s.param_key', '=', 'l.param_key')->on('s.effective_from', '=', 'l.effective_from'))
            ->orderBy('s.rule_code')
            ->get(['s.rule_code', 's.param_key', 's.param_value', 's.value_type', 's.effective_from'])
            ->all();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Valores vigentes')
            ->description('Un valor programado a futuro no aparece aquí hasta que entra en vigor.')
            ->records(fn (): array => collect($this->current())
                ->mapWithKeys(fn (object $row): array => ["{$row->rule_code}|{$row->param_key}" => (array) $row])
                ->all())
            ->columns([
                TextColumn::make('rule_code')->label('Regla')->weight(FontWeight::SemiBold),
                TextColumn::make('param_key')->label('Parámetro'),
                TextColumn::make('param_value')->label('Valor')->fontFamily(FontFamily::Mono),
                TextColumn::make('effective_from')->label('Vigente desde')
                    ->state(fn (array $record): string => Carbon::parse($record['effective_from'], 'UTC')->tz(config('clinic.timezone'))->format('d/m/Y H:i')),
            ])
            ->recordActions([ActionGroup::make([$this->scheduleAction()])])
            ->paginated(false);
    }

    private function scheduleAction(): Action
    {
        return Action::make('schedule')
            ->label('Programar cambio')
            ->icon(Heroicon::OutlinedCalendarDays)
            ->modalHeading(fn (array $record): string => "{$record['rule_code']} · {$record['param_key']}")
            ->schema(fn (array $record): array => [
                TextInput::make('value')->label('Valor nuevo')->required()
                    ->helperText(match ($record['value_type']) {
                        'int' => 'Número entero.',
                        'bool' => 'true o false.',
                        'json' => 'JSON válido, por ejemplo [{"h":24,"pct":0},{"h":1,"pct":50},{"h":0,"pct":100}].',
                        'duration' => 'Rango HH:MM-HH:MM, por ejemplo 21:00-07:00.',
                        default => '',
                    })
                    ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail) use ($record) {
                        if (! $this->isValid($record['value_type'], (string) $value)) {
                            $fail('El valor no tiene el formato de este parámetro.');
                        }
                    }),
                DateTimePicker::make('effective_from')->label('Vigente desde')->seconds(false)->default(now())->required(),
            ])
            ->action(function (array $record, array $data) {
                ['rule_code' => $rule, 'param_key' => $key] = $record;
                $before = app(BusinessRuleSettings::class)->value($rule, $key);

                DB::table('business_rule_settings')->insert([
                    'rule_code' => $rule, 'param_key' => $key, 'param_value' => $data['value'], 'value_type' => $record['value_type'],
                    'effective_from' => $data['effective_from'], 'updated_by' => auth()->id(),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                AuditLog::record('settings.changed', 'business_rule', null, [
                    'rule' => "{$rule}.{$key}",
                    'before' => $before,
                    'after' => $data['value'],
                    'effective_from' => $data['effective_from'],
                ]);
                Notification::make()->success()->title('Valor programado.')->send();
            });
    }

    private function isValid(string $type, string $value): bool
    {
        return match ($type) {
            'int' => filter_var($value, FILTER_VALIDATE_INT) !== false,
            'decimal' => is_numeric($value),
            'bool' => in_array($value, ['true', 'false'], true),
            'json' => json_validate($value),
            'duration' => preg_match('/^([01]\d|2[0-3]):[0-5]\d-([01]\d|2[0-3]):[0-5]\d$/', $value) === 1,
            default => false,
        };
    }
}
