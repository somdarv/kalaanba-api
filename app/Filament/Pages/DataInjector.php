<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\App;
use Kalaanba\Support\Auth\Role;

/**
 * DataInjector — controlled synthetic-data generator for dev/staging.
 *
 * Available recipes:
 *  - `users` — create N users with a chosen role via UserFactory
 *
 * Production guard: the page hides itself outside `local`, `testing`, and
 * `staging` so the route exists but is unreachable from the UI. The
 * inject action additionally re-checks env at runtime (defence in depth)
 * and refuses to mutate when env is `production`.
 *
 * Every successful inject is captured by the AdminAuditLogger middleware
 * (it's a POST mutating Livewire action — L5 still applies).
 *
 * @property-read array<string, mixed> $data
 * @property-read Form $form
 */
class DataInjector extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-beaker';

    protected static ?string $navigationGroup = 'God Mode';

    protected static ?string $navigationLabel = 'Data Injector';

    protected static ?int $navigationSort = 102;

    protected static string $view = 'filament.pages.data-injector';

    /** @var array<string, mixed> */
    public array $data = [];

    public ?string $lastResult = null;

    public static function shouldRegisterNavigation(): bool
    {
        return ! App::environment('production');
    }

    public function mount(): void
    {
        abort_if(App::environment('production'), 403, 'Data Injector is disabled in production.');
        $this->form->fill([
            'recipe' => 'users',
            'role' => Role::Fan->value,
            'count' => 3,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('recipe')
                    ->options(['users' => 'Create users via UserFactory'])
                    ->required(),
                Select::make('role')
                    ->options(collect(Role::cases())->mapWithKeys(fn (Role $r) => [$r->value => $r->value])->all())
                    ->required(),
                TextInput::make('count')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(50)
                    ->required(),
            ])
            ->statePath('data');
    }

    public function inject(): void
    {
        if (App::environment('production')) {
            Notification::make()->title('Refused: production environment')->danger()->send();

            return;
        }

        $state = $this->form->getState();
        $recipe = (string) ($state['recipe'] ?? '');

        if ($recipe !== 'users') {
            Notification::make()->title('Unknown recipe')->danger()->send();

            return;
        }

        $role = Role::from((string) ($state['role'] ?? Role::Fan->value));
        $count = max(1, min(50, (int) ($state['count'] ?? 1)));

        User::factory()->count($count)->withRole($role)->create();

        $this->lastResult = "Created {$count} user(s) with role {$role->value}.";

        Notification::make()->title($this->lastResult)->success()->send();
    }
}
