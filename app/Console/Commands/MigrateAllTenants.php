<?php

namespace Crater\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class MigrateAllTenants extends Command
{
    protected $signature = 'tenants:migrate-all';
    
    protected $description = 'Run migrations for all tenants';

    public function handle()
    {
        $tenants = Tenant::all();
        
        if ($tenants->count() === 0) {
            $this->info('No tenants found.');
            return 0;
        }
        
        $this->info("Migrating {$tenants->count()} tenants...");
        
        $bar = $this->output->createProgressBar($tenants->count());
        $bar->start();

        foreach ($tenants as $tenant) {
            try {
                tenancy()->initialize($tenant);
                
                Artisan::call('migrate', [
                    '--path' => 'database/migrations/tenant',
                    '--force' => true,
                ]);
                
                tenancy()->end();
                
                $bar->advance();
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("Failed to migrate tenant {$tenant->id}: " . $e->getMessage());
                tenancy()->end();
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info('All tenants migrated successfully!');
        
        return 0;
    }
}

