<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\SeasonResource\Pages;
use App\Models\Admin\Season;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * SeasonResource — read-only God Mode surface for the Season engine.
 *
 * Phase transitions are driven by `season:tick` (every 15 min) + Admin
 * Configuration. The panel exposes NO create / edit / delete because the
 * lifecycle is owned by the Season engine (Constitution §1.1, engine doc
 * docs/engines/season/). Manual overrides happen via AdminConfig + an audit
 * trail, not by hand-editing rows here.
 */
class SeasonResource extends Resource
{
    protected static ?string $model = Season::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Engines';

    protected static ?string $navigationLabel = 'Seasons';

    protected static ?int $navigationSort = 10;

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

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Season')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono'),
                BadgeColumn::make('phase')
                    ->colors([
                        'gray' => 'preseason',
                        'success' => 'active',
                        'warning' => 'peak',
                        'info' => 'run_in',
                        'danger' => 'closing',
                        'secondary' => 'archived',
                    ])
                    ->sortable(),
                TextColumn::make('starts_at')->dateTime()->sortable(),
                TextColumn::make('ends_at')->dateTime()->sortable(),
                TextColumn::make('participation_cutoff_at')
                    ->label('Participation cutoff')
                    ->dateTime()
                    ->toggleable(),
                TextColumn::make('closing_window_ends_at')
                    ->label('Closing ends')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('archive_window_ends_at')
                    ->label('Archive ends')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('archived_at')
                    ->dateTime()
                    ->toggleable(),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('phase')->options([
                    'preseason' => 'Preseason',
                    'active' => 'Active',
                    'peak' => 'Peak',
                    'run_in' => 'Run-in',
                    'closing' => 'Closing',
                    'archived' => 'Archived',
                ]),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->defaultSort('starts_at', 'desc');
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSeasons::route('/'),
        ];
    }
}
