<?php

namespace Crater\Console\Commands;

use App\Models\AdminUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminUser extends Command
{
    protected $signature = 'admin:create {name} {email} {password} {--update : Update existing user password if user exists}';
    
    protected $description = 'Create a new admin user for Filament panel';

    public function handle()
    {
        try {
            $email = $this->argument('email');
            $name = $this->argument('name');
            $password = $this->argument('password');
            
            // Устанавливаем search_path для текущей сессии
            \Illuminate\Support\Facades\DB::statement('SET search_path TO admin');
            
            // Проверяем, существует ли пользователь через DB напрямую
            $existing = \Illuminate\Support\Facades\DB::table('admin.users')
                ->where('email', $email)
                ->first();
            
            if ($existing) {
                if ($this->option('update')) {
                    // Обновляем пароль существующего пользователя
                    \Illuminate\Support\Facades\DB::table('admin.users')
                        ->where('email', $email)
                        ->update([
                            'name' => $name,
                            'password' => Hash::make($password),
                            'updated_at' => now(),
                        ]);
                    
                    $this->info("Admin user with email {$email} updated successfully!");
                    $this->info("ID: {$existing->id}");
                    $this->info("Email: {$email}");
                    $this->info("Login at: " . config('app.url') . '/admin/login');
                    return 0;
                } else {
                    $this->warn("Admin user with email {$email} already exists. Use --update flag to update password.");
                    return 1;
                }
            }
            
            // Создаем пользователя через DB напрямую, чтобы избежать проблем с Eloquent
            $userId = \Illuminate\Support\Facades\DB::table('admin.users')->insertGetId([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->info("Admin user created successfully!");
            $this->info("ID: {$userId}");
            $this->info("Email: {$email}");
            $this->info("Password: {$password}");
            $this->info("Login at: " . config('app.url') . '/admin/login');
            
            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to create admin user: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        }
    }
}

