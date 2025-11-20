<?php

namespace Crater\Console\Commands;

use App\Models\AdminUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminUser extends Command
{
    protected $signature = 'admin:create {name} {email} {password}';
    
    protected $description = 'Create a new admin user for Filament panel';

    public function handle()
    {
        try {
            $email = $this->argument('email');
            
            // Проверяем, существует ли пользователь
            $existing = AdminUser::where('email', $email)->first();
            if ($existing) {
                $this->info("Admin user with email {$email} already exists. Skipping creation.");
                return 0;
            }
            
            $admin = AdminUser::create([
                'name' => $this->argument('name'),
                'email' => $email,
                'password' => Hash::make($this->argument('password')),
            ]);

            $this->info("Admin user created successfully!");
            $this->info("Email: {$admin->email}");
            $this->info("Login at: " . config('app.url') . '/admin');
            
            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to create admin user: " . $e->getMessage());
            return 1;
        }
    }
}

