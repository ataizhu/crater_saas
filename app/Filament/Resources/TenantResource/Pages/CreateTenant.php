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
                ->title('Клиент создан и инициализирован!')
                ->body("Клиент готов!\n\nДля локальной разработки добавьте в /etc/hosts:\n127.0.0.1 {$this->record->id}.crater.test\n\nЗатем откройте: {$tenantUrl}")
                ->success()
                ->duration(10000)
                ->send();
        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('Клиент создан, но инициализация не удалась!')
                ->body("Запустите вручную: php artisan tenant:initialize {$this->record->id}\n\nОшибка: " . $e->getMessage())
                ->danger()
                ->duration(15000)
                ->send();
                
            \Log::error("Tenant initialization failed: " . $e->getMessage());
        }
    }
}
