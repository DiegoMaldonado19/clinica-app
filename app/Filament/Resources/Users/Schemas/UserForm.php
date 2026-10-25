<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Schemas\PersonFields;
use App\Identity\Infrastructure\TemporaryPassword;
use App\Models\Role;
use App\Models\User;
use App\Shared\Infrastructure\Audit\AuditLog;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Los pacientes nacen en Pacientes, con su ficha; aqui se da de alta al personal.
                Select::make('role_id')
                    ->label('Rol')
                    ->relationship('role', 'label', fn (Builder $query, string $operation) => $query->when(
                        $operation === 'create',
                        fn (Builder $query) => $query->where('code', '!=', 'patient'),
                    ))
                    ->helperText(fn (string $operation): ?string => $operation === 'create' ? 'Los pacientes se dan de alta en Pacientes, con su ficha.' : null)
                    ->required()
                    ->createOptionForm([
                        TextInput::make('label')
                            ->label('Nombre del rol')
                            ->maxLength(40)
                            ->required()
                            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                if (Role::query()->where('label', $value)->orWhere('code', self::roleCode((string) $value))->exists()) {
                                    $fail('Ya existe un rol con ese nombre.');
                                }
                            }),
                        CheckboxList::make('abilities')
                            ->label('Permisos')
                            ->options(fn (): array => DB::table('abilities')->where('is_active', true)->orderBy('code')->pluck('label', 'id')->all())
                            ->columns(2)
                            ->searchable()
                            ->bulkToggleable()
                            ->required(),
                    ])
                    ->createOptionModalHeading('Nuevo rol del personal')
                    ->createOptionUsing(fn (array $data): int => self::createRole($data['label'], $data['abilities'])),
                PersonFields::avatar(),
                ...PersonFields::make(),
                self::passwordField()
                    ->required(fn (string $operation): bool => $operation === 'create'),
                Toggle::make('is_active')
                    ->label('Activo')
                    ->default(true),
            ]);
    }

    /**
     * El mismo campo en el alta, la edicion y la accion del listado. Toda contraseña
     * que pone otra persona es temporal (RN-15); al editar, vacio conserva la actual.
     */
    public static function passwordField(): TextInput
    {
        return TextInput::make('password')
            ->label(fn (?User $record): string => $record?->must_change_password === false ? 'Cambio de contraseña' : 'Contraseña temporal')
            ->helperText('La persona deberá cambiarla al entrar.')
            ->password()
            ->revealable()
            ->copyable(copyMessage: 'Contraseña copiada')
            ->suffixAction(Action::make('generate')
                ->label('Generar contraseña')
                ->tooltip('Generar contraseña')
                ->icon(Heroicon::OutlinedSparkles)
                ->action(fn (TextInput $component) => $component->state(TemporaryPassword::generate())))
            ->rule(Password::defaults())
            ->dehydrated(fn (?string $state): bool => filled($state));
    }

    /**
     * ADR-040: un rol del personal con sus permisos. Entra al panel; lo que ve
     * dentro lo deciden los permisos marcados.
     *
     * @param  list<int|string>  $abilities
     */
    private static function createRole(string $label, array $abilities): int
    {
        return DB::transaction(function () use ($label, $abilities): int {
            $role = Role::create(['code' => self::roleCode($label), 'label' => $label]);

            DB::table('role_ability')->insert(array_map(
                fn (int|string $ability): array => ['role_id' => $role->id, 'ability_id' => (int) $ability],
                $abilities,
            ));
            AuditLog::record('role.created', 'role', (string) $role->id, ['abilities' => array_map('intval', $abilities)]);

            return $role->id;
        });
    }

    private static function roleCode(string $label): string
    {
        return Str::limit(Str::slug($label, '_'), 40, '');
    }
}
