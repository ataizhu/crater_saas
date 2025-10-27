<?php

namespace Crater\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SeedCountriesCommand extends Command
{
    protected $signature = 'tenant:seed-countries {tenantId?}';
    protected $description = 'Seed countries table for a specific tenant or all tenants.';

    public function handle()
    {
        $tenantId = $this->argument('tenantId');

        if ($tenantId) {
            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                $this->error("Tenant {$tenantId} not found!");
                return 1;
            }
            $this->seedCountriesForTenant($tenant);
        } else {
            // Seed for all tenants
            $tenants = Tenant::all();
            foreach ($tenants as $tenant) {
                $this->seedCountriesForTenant($tenant);
            }
        }

        $this->info("✅ Countries seeded successfully!");
        return 0;
    }

    private function seedCountriesForTenant($tenant)
    {
        $this->info("Seeding countries for tenant: {$tenant->id}");
        
        tenancy()->initialize($tenant);
        
        Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\CountriesTableSeeder',
            '--force' => true,
        ]);
        
        tenancy()->end();
    }
}

