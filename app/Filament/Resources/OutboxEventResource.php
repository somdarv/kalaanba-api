<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\OutboxEventResource\Pages;
use App\Models\Admin\OutboxEvent;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Artisan;

/**
 * OutboxEventResource — God Mode read+replay surface for the outbox.
 *
 * Records are append-only; the only action is "Re-emit" which clears
 * `delivered_at` so the next outbox:relay pass re-publishes the event.
 */
class OutboxEventResource extends Resource
{
    protected static ?string $model = OutboxEvent::class;

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $navigationGroup = 'EventBus';

    protected static ?string $navigationLabel = 'Outbox events';

    protected static ?int $navigationSort = 20;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('event_name')->searchable()->sortable(),
                TextColumn::make('event_id')->copyable()->fontFamily('mono')->limit(13)->tooltip(fn ($state) => (string) $state),
                TextColumn::make('schema_version')->label('v')->sortable(),
                IconColumn::make('delivered_at')->label('Delivered')->boolean(),
                TextColumn::make('attempts')->sortable(),
                TextColumn::make('last_error')->limit(60)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('occurred_at')->dateTime()->sortable(),
                TextColumn::make('delivered_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('delivered_at')
                    ->label('Delivery status')
                    ->placeholder('All')
                    ->trueLabel('Delivered only')
                    ->falseLabel('Pending only')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('delivered_at'),
                        false: fn (Builder $q) => $q->whereNull('delivered_at'),
                        blank: fn (Builder $q) => $q,
                    ),
                Filter::make('has_error')
                    ->label('Has error')
                    ->query(fn (Builder $q) => $q->whereNotNull('last_error')),
            ])
            ->actions([
                ViewAction::make(),
                Action::make('reemit')
                    ->label('Re-emit')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Clears delivered_at + last_error and resets attempts. Next outbox:relay pass will republish this event.')
                    ->action(function (OutboxEvent $event): void {
                        $event->forceFill([
                            'delivered_at' => null,
                            'last_error' => null,
                            'attempts' => 0,
                        ])->save();
                        try {
                            Artisan::call('outbox:relay', ['--once' => true]);
                        } catch (\Throwable) {
                            // Relay command may be absent in some envs; not fatal.
                        }
                        Notification::make()->title('Re-emit queued')->success()->send();
                    }),
            ])
            ->defaultSort('occurred_at', 'desc');
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOutboxEvents::route('/'),
        ];
    }
}
