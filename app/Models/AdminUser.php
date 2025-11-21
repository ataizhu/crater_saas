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
     * Get the table associated with the model.
     * 
     * Note: Schema is already set in config/database.php ('schema' => 'admin'),
     * so we don't need to prepend it here. Laravel will automatically use
     * the schema from the database configuration.
     */
    public function getTable()
    {
        // Не добавляем схему вручную, так как она уже указана в config/database.php
        // Laravel автоматически использует схему из конфигурации подключения
        return $this->table;
    }

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder|static
     */
    public function newEloquentBuilder($query)
    {
        // Устанавливаем search_path перед каждым запросом для модели AdminUser
        // Это гарантирует, что запросы идут в схему admin
        $query->getConnection()->statement('SET search_path TO admin');
        
        return parent::newEloquentBuilder($query);
    }

    public function canAccessFilament(): bool
    {
        return true; // В будущем можно добавить дополнительную логику
    }
}

