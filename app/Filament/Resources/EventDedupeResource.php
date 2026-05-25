<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\EventDedupeResource\Pages;
use App\Models\Admin\EventDedupe;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * EventDedupeResource — read-only listener idempotency state.
 *
 * Surfacing this lets operators see which (event_id, listener) pairs have
 * already been processed. Mutating these rows manually risks replay-storms,
 * so no write actions are exposed (L6, L14).
 */
class EventDedupeResource extends Resource
{
    protected static ?string $model = EventDedupe::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'EventBus';

    protected static ?string $navigationLabel = 'Event dedupe';

    protected static ?int $navigationSort = 21;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('event_id')->fontFamily('mono')->searchable()->limit(20)->tooltip(fn ($state) => (string) $state),
                TextColumn::make('listener_name')->searchable(),
                TextColumn::make('processed_at')->dateTime()->sortable(),
            ])
            ->defaultSort('processed_at', 'desc');
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEventDedupes::route('/'),
        ];
    }
}
