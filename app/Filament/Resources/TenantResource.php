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
    
    protected static ?string $navigationLabel = 'Tenants';
    
    protected static ?string $pluralLabel = 'Tenants';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Placeholder::make('url')
                    ->label('Tenant URL')
                    ->content(fn ($record) => $record ? 'http://' . $record->id . '.' . config('app.main_domain') : 'Will be generated after creation')
                    ->hidden(fn ($livewire) => $livewire instanceof Pages\CreateTenant),
                    
                Forms\Components\TextInput::make('id')
                    ->label('Subdomain')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->helperText('Will be accessible at: subdomain.' . config('app.main_domain'))
                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/'),
                    
                Forms\Components\TextInput::make('name')
                    ->label('Company Name')
                    ->required()
                    ->maxLength(255),
                    
                Forms\Components\TextInput::make('owner_name')
                    ->label('Owner Name')
                    ->required()
                    ->maxLength(255),
                    
                Forms\Components\TextInput::make('owner_email')
                    ->label('Owner Email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                    
                Forms\Components\TextInput::make('owner_password')
                    ->label('Owner Password')
                    ->password()
                    ->required(fn ($livewire) => $livewire instanceof Pages\CreateTenant)
                    ->dehydrated(fn ($state) => filled($state))
                    ->minLength(8)
                    ->maxLength(255)
                    ->helperText('Leave empty to keep current password when editing.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Subdomain')
                    ->searchable()
                    ->sortable()
                    ->url(fn ($record) => 'http://' . $record->id . '.' . config('app.main_domain'))
                    ->openUrlInNewTab(),
                    
                Tables\Columns\TextColumn::make('name')
                    ->label('Company Name')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('owner_name')
                    ->label('Owner')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('owner_email')
                    ->label('Email')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('visit')
                    ->label('Visit Site')
                    ->icon('heroicon-o-external-link')
                    ->url(fn ($record) => 'http://' . $record->id . '.' . config('app.main_domain'))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
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
