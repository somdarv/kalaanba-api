<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AdminConfigResource\Pages;
use App\Models\Admin\AdminConfig;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * AdminConfigResource — effective-dated CRUD for the Admin Configuration Engine.
 *
 * Constitution L2 lives here: every domain knob, threshold, percentage,
 * label, and toggle is administered through this table. Edits create new
 * effective rows (immutable history). The Filament form intentionally
 * mirrors the Admin Governance migration columns 1:1.
 */
class AdminConfigResource extends Resource
{
    protected static ?string $model = AdminConfig::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Configuration';

    protected static ?string $navigationLabel = 'Config keys';

    protected static ?int $navigationSort = 40;

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('key')->required()->maxLength(255),
            Select::make('scope')
                ->options(['platform' => 'platform', 'zone' => 'zone', 'club' => 'club', 'user' => 'user'])
                ->required()
                ->default('platform'),
            TextInput::make('scope_id')->label('Scope ID (UUID)')->maxLength(36),
            Textarea::make('value')->required()->rows(3),
            DateTimePicker::make('effective_from')->required()->default(now()),
            TextInput::make('version')->numeric()->default(1)->required(),
            Select::make('approval_level')
                ->options(['low' => 'low', 'medium' => 'medium', 'high' => 'high'])
                ->default('low')
                ->required(),
            TextInput::make('approved_by')->label('Approved by (user UUID)')->maxLength(36),
            Textarea::make('change_reason')->rows(2)->placeholder('Why this change? (audit trail)'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')->searchable()->sortable(),
                TextColumn::make('scope')->badge(),
                TextColumn::make('scope_id')->limit(13)->tooltip(fn ($state) => (string) $state),
                TextColumn::make('value')->limit(40)->tooltip(fn ($state) => (string) $state),
                TextColumn::make('effective_from')->dateTime()->sortable(),
                TextColumn::make('version')->sortable(),
                TextColumn::make('approval_level')->badge(),
            ])
            ->filters([
                SelectFilter::make('scope')
                    ->options(['platform' => 'platform', 'zone' => 'zone', 'club' => 'club', 'user' => 'user']),
                SelectFilter::make('approval_level')
                    ->options(['low' => 'low', 'medium' => 'medium', 'high' => 'high']),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->defaultSort('effective_from', 'desc');
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdminConfigs::route('/'),
            'create' => Pages\CreateAdminConfig::route('/create'),
            'edit' => Pages\EditAdminConfig::route('/{record}/edit'),
        ];
    }
}
