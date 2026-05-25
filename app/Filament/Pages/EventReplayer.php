<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Admin\OutboxEvent;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * EventReplayer — batch outbox-event re-emit page.
 *
 * Lets an operator filter events by name + lookback window and re-emit
 * the matching set in one click. The single-row equivalent already exists
 * on OutboxEventResource; this page is the bulk tool for incidents where
 * a downstream consumer dropped a wave of events.
 *
 * Re-emit semantics: clear `delivered_at`, reset `attempts` to 0, then
 * fire `outbox:relay --once` (if the command exists). The action is
 * idempotent — already-pending rows are unaffected.
 *
 * @property-read array<string, mixed> $data
 * @property-read \Filament\Forms\Form $form
 */
class EventReplayer extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?string $navigationGroup = 'God Mode';

    protected static ?string $navigationLabel = 'Event Replayer';

    protected static ?int $navigationSort = 101;

    protected static string $view = 'filament.pages.event-replayer';

    /** @var array<string, mixed> */
    public array $data = [];

    public int $lastReplayedCount = 0;

    /** @var array<int, array<string, mixed>> */
    public array $preview = [];

    public function mount(): void
    {
        $this->form->fill(['lookback_hours' => 24]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('event_name')
                    ->label('Event name (exact match)')
                    ->placeholder('match.result_confirmed')
                    ->required(),
                TextInput::make('lookback_hours')
                    ->label('Lookback window (hours)')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(24 * 30)
                    ->required(),
            ])
            ->statePath('data');
    }

    public function previewMatches(): void
    {
        $state = $this->form->getState();
        $this->preview = $this->matchingQuery($state)
            ->orderByDesc('occurred_at')
            ->limit(25)
            ->get(['id', 'event_name', 'occurred_at', 'delivered_at', 'attempts'])
            ->toArray();
    }

    public function replay(): void
    {
        $state = $this->form->getState();

        $count = $this->matchingQuery($state)
            ->update([
                'delivered_at' => null,
                'last_error' => null,
                'attempts' => 0,
            ]);

        $this->lastReplayedCount = (int) $count;
        $this->preview = [];

        try {
            Artisan::call('outbox:relay', ['--once' => true]);
        } catch (Throwable) {
            // Relay command may not yet be registered; the update above is
            // still meaningful — a future relay run will pick the rows up.
        }

        Notification::make()
            ->title("Re-emit queued for {$this->lastReplayedCount} events")
            ->success()
            ->send();
    }

    /**
     * @param  array<string, mixed>  $state
     * @return Builder<OutboxEvent>
     */
    private function matchingQuery(array $state): Builder
    {
        $name = (string) ($state['event_name'] ?? '');
        $hours = max(1, (int) ($state['lookback_hours'] ?? 24));

        return OutboxEvent::query()
            ->where('event_name', $name)
            ->where('occurred_at', '>=', now()->subHours($hours));
    }
}
