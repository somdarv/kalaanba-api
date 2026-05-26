<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ZoneResource\Pages;
use App\Models\Admin\Zone;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Read-only God Mode surface for `zones` (kind = zone | belt). */
class ZoneResource extends Resource
{
    protected static ?string $model = Zone::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Engines';

    protected static ?string $navigationLabel = 'Zones';

    protected static ?int $navigationSort = 23;

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
                TextColumn::make('code')->fontFamily('mono')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                BadgeColumn::make('kind')->colors([
                    'primary' => 'zone',
                    'warning' => 'belt',
                ])->sortable(),
                TextColumn::make('city_hub_id')->label('City Hub')->fontFamily('mono')->toggleable(),
                TextColumn::make('created_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('kind')->options([
                    'zone' => 'Zone',
                    'belt' => 'Belt',
                ]),
            ])
            ->actions([ViewAction::make()])
            ->defaultSort('name');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListZones::route('/'),
        ];
    }
}
