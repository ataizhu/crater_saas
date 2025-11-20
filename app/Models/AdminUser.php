<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class AdminUser extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * The schema name for PostgreSQL.
     *
     * @var string
     */
    protected $schema = 'admin';

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Override the table name to include schema for PostgreSQL.
     */
    public function getTable()
    {
        // Для PostgreSQL явно указываем схему
        if (config('database.default') === 'pgsql') {
            return $this->schema . '.' . $this->table;
        }
        
        return $this->table;
    }

    public function canAccessFilament(): bool
    {
        return true; // В будущем можно добавить дополнительную логику
    }
}

