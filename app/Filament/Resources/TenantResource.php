<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TenantResource\Pages;
use App\Filament\Resources\TenantResource\RelationManagers;
use App\Models\Tenant;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-office-building';
    
    protected static ?string $navigationLabel = 'Клиенты';
    
    protected static ?string $pluralLabel = 'Клиенты';
    
    protected static ?string $label = 'Клиент';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Placeholder::make('url')
                    ->label('URL клиента')
                    ->content(fn ($record) => $record ? 'http://' . $record->id . '.' . config('app.main_domain') : 'Будет создан после сохранения')
                    ->hidden(fn ($livewire) => $livewire instanceof Pages\CreateTenant),
                    
                Forms\Components\TextInput::make('id')
                    ->label('Субдомен')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->helperText('Будет доступен на: subdomain.crater.test')
                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/'),
                    
                Forms\Components\TextInput::make('name')
                    ->label('Название компании')
                    ->required()
                    ->maxLength(255),
                    
                Forms\Components\TextInput::make('owner_name')
                    ->label('Имя владельца')
                    ->required()
                    ->maxLength(255),
                    
                Forms\Components\TextInput::make('owner_email')
                    ->label('Email владельца')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                    
                Forms\Components\TextInput::make('owner_password')
                    ->label('Пароль владельца')
                    ->password()
                    ->required(fn ($livewire) => $livewire instanceof Pages\CreateTenant)
                    ->dehydrated(fn ($state) => filled($state))
                    ->minLength(8)
                    ->maxLength(255)
                    ->helperText('Оставьте пустым чтобы сохранить текущий пароль при редактировании.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Субдомен')
                    ->searchable()
                    ->sortable()
                    ->url(fn ($record) => 'http://' . $record->id . '.' . config('app.main_domain'))
                    ->openUrlInNewTab(),
                    
                Tables\Columns\TextColumn::make('name')
                    ->label('Название компании')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('owner_name')
                    ->label('Владелец')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('owner_email')
                    ->label('Email')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('visit')
                    ->label('Открыть сайт')
                    ->icon('heroicon-o-external-link')
                    ->url(fn ($record) => 'http://' . $record->id . '.' . config('app.main_domain'))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make()
                    ->label('Редактировать'),
                Tables\Actions\DeleteAction::make()
                    ->label('Удалить'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->label('Удалить выбранные'),
            ]);
    }
    
    public static function getRelations(): array
    {
        return [
            //
        ];
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }    
}
