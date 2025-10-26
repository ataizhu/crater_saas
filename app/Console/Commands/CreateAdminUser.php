<?php

namespace App\Console\Commands;

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
            $admin = AdminUser::create([
                'name' => $this->argument('name'),
                'email' => $this->argument('email'),
                'password' => Hash::make($this->argument('password')),
            ]);

            $this->info("Admin user created successfully!");
            $this->info("Email: {$admin->email}");
            $this->info("Login at: http://crater.test/admin");
            
            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to create admin user: " . $e->getMessage());
            return 1;
        }
    }
}

