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
        // Инициализация отключена - запускать вручную командой:
        // php artisan tenant:initialize {tenant_id}
        // InitializeTenantJob::dispatchSync($this->record);
        
        $tenantUrl = 'http://' . $this->record->id . '.' . config('app.main_domain');
        
        \Filament\Notifications\Notification::make()
            ->title('Tenant created!')
            ->body("1. Run: php artisan tenant:initialize {$this->record->id}\n2. Add to /etc/hosts: 127.0.0.1 {$this->record->id}.crater.test\n3. Visit: {$tenantUrl}")
            ->success()
            ->send();
    }
}
