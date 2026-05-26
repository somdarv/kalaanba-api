<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AreaSuggestionResource\Pages;
use App\Models\Admin\AreaSuggestion;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only God Mode surface for `area_suggestions`.
 *
 * Approval / rejection happens in the native admin panel (Kalaanba UI),
 * NOT Filament — per platform direction "filament is for the godmode thing".
 */
class AreaSuggestionResource extends Resource
{
    protected static ?string $model = AreaSuggestion::class;

    protected static ?string $navigationIcon = 'heroicon-o-light-bulb';

    protected static ?string $navigationGroup = 'Engines';

    protected static ?string $navigationLabel = 'Area Suggestions';

    protected static ?int $navigationSort = 25;

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
                TextColumn::make('proposed_name')->label('Proposed')->searchable()->sortable(),
                BadgeColumn::make('status')->colors([
                    'warning' => 'pending',
                    'success' => 'approved',
                    'danger' => 'rejected',
                ])->sortable(),
                TextColumn::make('city_hub_id')->label('City Hub')->fontFamily('mono')->toggleable(),
                TextColumn::make('proposed_zone_id')->label('Proposed Zone')->fontFamily('mono')->toggleable(),
                TextColumn::make('submitted_by_user_id')->label('Submitter')->fontFamily('mono')->toggleable(),
                TextColumn::make('submitted_at')->dateTime()->sortable(),
                TextColumn::make('reviewed_at')->dateTime()->toggleable(),
                TextColumn::make('resulting_area_id')->label('Area')->fontFamily('mono')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                ]),
            ])
            ->actions([ViewAction::make()])
            ->defaultSort('submitted_at', 'desc');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAreaSuggestions::route('/'),
        ];
    }
}
