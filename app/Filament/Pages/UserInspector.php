<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Admin\AdminAuditLog;
use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

/**
 * UserInspector — God Mode operator surface for deep user introspection.
 *
 * Given an email OR a user UUID, surfaces:
 *  - the user row (identity, role, archive status, last_seen_at)
 *  - the last 50 audit-log entries authored by that user (L5 trace)
 *
 * READ-ONLY. Mutating actions on a user (reset password, archive, etc.)
 * happen via the standard UserResource — keeping inspector concerns
 * separate from administration concerns.
 *
 * @property-read array<string, mixed> $data
 * @property-read \Filament\Forms\Form $form
 */
class UserInspector extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-magnifying-glass';

    protected static ?string $navigationGroup = 'God Mode';

    protected static ?string $navigationLabel = 'User Inspector';

    protected static ?int $navigationSort = 100;

    protected static string $view = 'filament.pages.user-inspector';

    /** @var array<string, mixed> */
    public array $data = [];

    public ?User $foundUser = null;

    /** @var array<int, array<string, mixed>> */
    public array $recentAuditEntries = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('query')
                    ->label('Email or user UUID')
                    ->required()
                    ->placeholder('you@kalaanba.local or 0190f...uuid'),
            ])
            ->statePath('data');
    }

    public function search(): void
    {
        $state = $this->form->getState();
        $query = trim((string) ($state['query'] ?? ''));

        if ($query === '') {
            return;
        }

        $user = User::query()
            ->where('email', $query)
            ->orWhere('id', $query)
            ->first();

        $this->foundUser = $user;

        if ($user === null) {
            $this->recentAuditEntries = [];

            return;
        }

        $this->recentAuditEntries = AdminAuditLog::query()
            ->where('actor_id', (string) $user->getAuthIdentifier())
            ->orderByDesc('occurred_at')
            ->limit(50)
            ->get(['id', 'method', 'path', 'response_status', 'occurred_at'])
            ->toArray();
    }
}
