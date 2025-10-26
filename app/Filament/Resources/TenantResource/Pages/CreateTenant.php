<?php

namespace App\Filament\Resources\TenantResource\Pages;

use App\Filament\Resources\TenantResource;
use App\Jobs\InitializeTenantJob;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    protected function afterCreate(): void
    {
        $tenantUrl = 'http://' . $this->record->id . '.' . config('app.main_domain');
        
        try {
            // Автоматическая инициализация тенанта
            InitializeTenantJob::dispatchSync($this->record);
            
            \Filament\Notifications\Notification::make()
                ->title('Tenant created & initialized!')
                ->body("Tenant ready!\n\nFor local development, add to /etc/hosts:\n127.0.0.1 {$this->record->id}.crater.test\n\nThen visit: {$tenantUrl}")
                ->success()
                ->duration(10000)
                ->send();
        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('Tenant created, but initialization failed!')
                ->body("Run manually: php artisan tenant:initialize {$this->record->id}\n\nError: " . $e->getMessage())
                ->danger()
                ->duration(15000)
                ->send();
                
            \Log::error("Tenant initialization failed: " . $e->getMessage());
        }
    }
}
