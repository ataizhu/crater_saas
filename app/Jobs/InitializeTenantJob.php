<?php

namespace App\Jobs;

use App\Mail\TenantCreatedMail;
use App\Models\Tenant;
use Crater\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class InitializeTenantJob
{
    use Dispatchable;

    public function __construct(public Tenant $tenant)
    {
    }

    public function handle(): void
    {
        try {
            \Log::info("Initializing tenant: {$this->tenant->id}");
            
            // Читаем данные напрямую из БД (Eloquent может не видеть data после переопределений)
            $tenantData = DB::table('tenants')->where('id', $this->tenant->id)->first();
            $data = json_decode($tenantData->data, true);
            
            if (!$data) {
                throw new \Exception("Tenant data is empty for tenant: {$this->tenant->id}");
            }
            
            // Создаем схему (prefix from config/tenancy.php)
            $prefix = config('tenancy.database.prefix', 'tenant');
            $schemaName = $prefix . $this->tenant->id;
            \Log::info("Creating schema: {$schemaName}");
            DB::statement("CREATE SCHEMA IF NOT EXISTS {$schemaName}");
            
            // Переключаемся на схему тенанта
            tenancy()->initialize($this->tenant);

            // Запускаем миграции в схеме тенанта
            \Log::info("Running migrations for tenant: {$this->tenant->id}");
            Artisan::call('migrate', [
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);

            // Создаем компанию
            \Log::info("Creating company for tenant: {$this->tenant->id}");
            $company = \Crater\Models\Company::create([
                'name' => $data['name'],
                'unique_hash' => \Illuminate\Support\Str::random(20),
            ]);
            
            // Создаем owner пользователя в схеме тенанта (если его еще нет)
            // Примечание: User модель автоматически хеширует пароль через setPasswordAttribute
            \Log::info("Creating owner user for tenant: {$this->tenant->id}");
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
            $user->companies()->sync([$company->id]);
            
            // Настраиваем компанию: роли, права, методы оплаты, единицы измерения, настройки
            \Log::info("Setting up company defaults for tenant: {$this->tenant->id}");
            $company->setupDefaultData();
            
            // Назначаем роль super admin пользователю
            \Silber\Bouncer\BouncerFacade::scope()->to($company->id);
            \Silber\Bouncer\BouncerFacade::assign('super admin')->to($user);
            
            // Создаем маркер что установка завершена
            \Storage::disk('local')->put('database_created', now());
            
            // Устанавливаем profile_complete
            \Crater\Models\Setting::setSetting('profile_complete', 'COMPLETED');

            tenancy()->end();

            // Отправляем email с доступами (если настроен mail driver)
            try {
                $loginUrl = 'http://' . $this->tenant->domains->first()->domain;
                
                if (config('mail.driver') !== 'log' && config('mail.driver') !== null) {
                    Mail::to($data['owner_email'])->send(
                        new TenantCreatedMail($this->tenant, $loginUrl)
                    );
                    \Log::info("Tenant creation email sent to: {$data['owner_email']}");
                }
            } catch (\Exception $emailError) {
                // Email не критичен - просто логируем ошибку
                \Log::warning("Failed to send tenant creation email: " . $emailError->getMessage());
            }
            
            \Log::info("Tenant {$this->tenant->id} initialized successfully!");
        } catch (\Exception $e) {
            tenancy()->end();
            \Log::error("Tenant initialization failed for {$this->tenant->id}: " . $e->getMessage());
            \Log::error($e->getTraceAsString());
            throw $e;
        }
    }
}

