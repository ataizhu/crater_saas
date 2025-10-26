<?php

namespace App\Filament\Resources\TenantResource\Pages;

use App\Filament\Resources\TenantResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    protected function getActions(): array
    {
        return [
            Actions\Action::make('visit')
                ->label('Открыть сайт')
                ->icon('heroicon-o-external-link')
                ->url(fn () => 'http://' . $this->record->id . '.' . config('app.main_domain'))
                ->openUrlInNewTab()
                ->color('success'),
            Actions\DeleteAction::make()
                ->label('Удалить'),
        ];
    }
}
