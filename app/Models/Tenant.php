<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Hash;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains, HasFactory;

    protected $fillable = [
        'id',
        'name',
        'owner_name',
        'owner_email',
        'owner_password',
    ];

    protected $hidden = [
        'owner_password',
    ];

    public static function booted()
    {
        parent::booted();

        static::created(function ($tenant) {
            // Создание домена для субдомена
            $tenant->domains()->create([
                'domain' => $tenant->id . '.' . config('app.main_domain'),
            ]);
        });
    }
    
    // Пароль НЕ хешируем здесь - хеширование происходит только при создании User в InitializeTenantCommand
}

