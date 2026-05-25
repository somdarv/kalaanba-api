<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AnalyticsEventResource\Pages;
use App\Models\Admin\AnalyticsEvent;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * AnalyticsEventResource — read-only window into the partitioned
 * `analytics.events` table. Analytics is downstream-only (L6).
 */
class AnalyticsEventResource extends Resource
{
    protected static ?string $model = AnalyticsEvent::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Observability';

    protected static ?string $navigationLabel = 'Analytics events';

    protected static ?int $navigationSort = 50;

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
                TextColumn::make('occurred_at')->dateTime()->sortable(),
                TextColumn::make('event_name')->searchable()->sortable(),
                TextColumn::make('source')->badge(),
                TextColumn::make('actor_role')->badge()->toggleable(),
                TextColumn::make('actor_user_id')->fontFamily('mono')->limit(13)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('schema_version')->label('v')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('event_id')->fontFamily('mono')->limit(13)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('source')
                    ->options(['api' => 'api', 'system' => 'system', 'replay' => 'replay']),
            ])
            ->actions([ViewAction::make()])
            ->defaultSort('occurred_at', 'desc');
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAnalyticsEvents::route('/'),
        ];
    }
}
