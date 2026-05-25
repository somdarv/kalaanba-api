<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AdminAuditLogResource\Pages;
use App\Models\Admin\AdminAuditLog;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * AdminAuditLogResource — read-only ledger of every privileged action.
 * Append-only per Constitution L5; no Create/Edit/Delete exposed.
 */
class AdminAuditLogResource extends Resource
{
    protected static ?string $model = AdminAuditLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Compliance';

    protected static ?string $navigationLabel = 'Audit log';

    protected static ?int $navigationSort = 30;

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
                TextColumn::make('actor_id')->fontFamily('mono')->limit(13)->tooltip(fn ($state) => (string) $state),
                TextColumn::make('actor_role')->badge(),
                TextColumn::make('method')->badge(),
                TextColumn::make('path')->searchable()->limit(60)->tooltip(fn ($state) => (string) $state),
                TextColumn::make('response_status')->label('Status')->sortable(),
                TextColumn::make('request_id')->fontFamily('mono')->limit(13)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('actor_role')
                    ->options([
                        'super_admin' => 'super_admin',
                        'hub_admin' => 'hub_admin',
                        'club_admin' => 'club_admin',
                        'referee' => 'referee',
                        'player' => 'player',
                        'fan' => 'fan',
                    ]),
                SelectFilter::make('method')->options([
                    'GET' => 'GET', 'POST' => 'POST', 'PATCH' => 'PATCH', 'PUT' => 'PUT', 'DELETE' => 'DELETE',
                ]),
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
            'index' => Pages\ListAdminAuditLogs::route('/'),
        ];
    }
}
