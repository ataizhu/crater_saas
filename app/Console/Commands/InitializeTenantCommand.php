<?php

namespace Crater\Console\Commands;

use App\Models\Tenant;
use Crater\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class InitializeTenantCommand extends Command
{
    protected $signature = 'tenant:initialize {tenant_id}';
    
    protected $description = 'Initialize a tenant: create schema, run migrations, create owner user';

    public function handle()
    {
        $tenantId = $this->argument('tenant_id');
        $tenant = Tenant::find($tenantId);
        
        if (!$tenant) {
            $this->error("Tenant {$tenantId} not found!");
            return 1;
        }
        
        $this->info("Initializing tenant: {$tenant->id}");
        
        // Читаем данные напрямую из БД (Eloquent может не видеть data после переопределений)
        $tenantData = DB::table('tenants')->where('id', $tenantId)->first();
        $data = json_decode($tenantData->data, true);
        
        if (!$data) {
            $this->error("Tenant data is empty!");
            return 1;
        }
        
        $this->info("Owner: {$data['owner_name']} ({$data['owner_email']})");
        
        // Создаем схему (prefix from config/tenancy.php)
        $prefix = config('tenancy.database.prefix', 'tenant');
        $schemaName = $prefix . $tenant->id;
        $this->info("Creating schema: {$schemaName}");
        DB::statement("CREATE SCHEMA IF NOT EXISTS {$schemaName}");
        
        // Переключаемся на схему тенанта
        tenancy()->initialize($tenant);
        
        // Запускаем миграции
        $this->info("Running migrations...");
        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);
        
        $this->info(Artisan::output());
        
        // Создаем компанию
        $this->info("Creating company...");
        $company = \Crater\Models\Company::create([
            'name' => $data['name'],
            'unique_hash' => \Illuminate\Support\Str::random(20),
        ]);
        
        // Создаем owner пользователя (если его еще нет)
        // Примечание: User модель автоматически хеширует пароль через setPasswordAttribute
        $this->info("Creating owner user...");
        $user = User::firstOrCreate(
            ['email' => $data['owner_email']],
            [
                'name' => $data['owner_name'],
                'password' => $data['owner_password'], // Plain password - будет захеширован автоматически
                'role' => 'super admin',
            ]
        );
        
        // Привязываем пользователя к компании
        $company->update(['owner_id' => $user->id]);
        $user->companies()->attach($company->id);
        
        // Создаем маркер что установка завершена (внутри контекста тенанта)
        \Storage::disk('local')->put('database_created', now());
        
        // Устанавливаем profile_complete (внутри контекста тенанта)
        \Crater\Models\Setting::setSetting('profile_complete', 'COMPLETED');
        
        tenancy()->end();
        
        $this->info("✅ Tenant {$tenant->id} initialized successfully!");
        $this->info("Login URL: http://{$tenant->id}.crater.test");
        $this->info("Email: {$data['owner_email']}");
        
        return 0;
    }
}

