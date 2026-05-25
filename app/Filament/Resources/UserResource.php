<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Kalaanba\Support\Auth\Role;
use STS\FilamentImpersonate\Tables\Actions\Impersonate;

/**
 * UserResource — God Mode user management surface (ADR-0002).
 *
 * Honours Constitution L13 (archive, don't delete): the DeleteAction is
 * replaced with a custom ArchiveAction that flips `archived_at`. Filament's
 * default delete is removed everywhere — bulk delete also dropped.
 *
 * Honours L5 (audit): every Reset Password / Archive / Impersonate action
 * is logged via the AdminAudit middleware that wraps the panel routes.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Identity';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
            Select::make('role')
                ->options(collect(Role::cases())->mapWithKeys(fn (Role $r) => [$r->value => $r->value])->all())
                ->required(),
            TextInput::make('phone_e164_last4')->label('Phone (last 4)')->maxLength(4),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->sortable(),
                TextColumn::make('role')->badge()->formatStateUsing(fn ($state) => $state instanceof Role ? $state->value : (string) $state),
                IconColumn::make('archived_at')
                    ->label('Active')
                    ->boolean()
                    ->getStateUsing(fn (User $u): bool => $u->archived_at === null),
                TextColumn::make('last_seen_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->options(collect(Role::cases())->mapWithKeys(fn (Role $r) => [$r->value => $r->value])->all()),
                TernaryFilter::make('archived_at')
                    ->label('Archive status')
                    ->placeholder('All users')
                    ->trueLabel('Archived only')
                    ->falseLabel('Active only')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('archived_at'),
                        false: fn (Builder $q) => $q->whereNull('archived_at'),
                        blank: fn (Builder $q) => $q,
                    ),
            ])
            ->actions([
                EditAction::make(),
                Impersonate::make()->redirectTo('/'),
                Action::make('resetPassword')
                    ->label('Reset password')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->form([
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(8)
                            ->label('New password'),
                    ])
                    ->requiresConfirmation()
                    ->action(function (User $user, array $data): void {
                        $user->forceFill(['password' => Hash::make($data['password'])])->save();
                    }),
                Action::make('archive')
                    ->label('Archive')
                    ->icon('heroicon-o-archive-box')
                    ->color('danger')
                    ->visible(fn (User $u): bool => $u->archived_at === null)
                    ->requiresConfirmation()
                    ->modalDescription('Archiving preserves history but prevents login. This replaces delete (Constitution L13).')
                    ->action(function (User $user): void {
                        $user->forceFill(['archived_at' => now()])->save();
                    }),
                Action::make('restore')
                    ->label('Restore')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('success')
                    ->visible(fn (User $u): bool => $u->archived_at !== null)
                    ->requiresConfirmation()
                    ->action(function (User $user): void {
                        $user->forceFill(['archived_at' => null])->save();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    // Bulk delete deliberately omitted (L13). Add bulk archive if needed later.
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
