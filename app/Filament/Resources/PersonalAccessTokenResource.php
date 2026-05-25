<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\PersonalAccessTokenResource\Pages;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * PersonalAccessTokenResource — list + revoke Sanctum API tokens.
 *
 * Revoke = hard delete of the token row (per Sanctum design); audit is
 * captured by the AdminAudit middleware (L5). No bulk revoke.
 */
class PersonalAccessTokenResource extends Resource
{
    protected static ?string $model = PersonalAccessToken::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationGroup = 'Identity';

    protected static ?string $navigationLabel = 'API tokens';

    protected static ?int $navigationSort = 11;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('tokenable_type')->label('Owner type')->limit(28),
                TextColumn::make('tokenable_id')->label('Owner ID')->fontFamily('mono')->limit(13)->tooltip(fn ($state) => (string) $state),
                TextColumn::make('abilities')->limit(40)->toggleable(),
                TextColumn::make('last_used_at')->dateTime()->sortable(),
                TextColumn::make('expires_at')->dateTime()->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->actions([
                Action::make('revoke')
                    ->label('Revoke')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Revoking a token immediately invalidates it for the API.')
                    ->action(function (PersonalAccessToken $token): void {
                        $token->delete();
                        Notification::make()->title('Token revoked')->success()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPersonalAccessTokens::route('/'),
        ];
    }
}
