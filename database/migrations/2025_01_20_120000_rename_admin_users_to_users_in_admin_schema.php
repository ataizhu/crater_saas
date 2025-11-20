<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RenameAdminUsersToUsersInAdminSchema extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (config('database.default') !== 'pgsql') {
            return;
        }

        // Переименовываем таблицу admin_users в users в схеме admin
        // Это нужно для того, чтобы Filament мог использовать стандартную таблицу users
        // и не было конфликта с таблицей users в схемах тенантов
        // Таблицы изолированы по схемам: admin.users (админы) и tenantXXX.users (пользователи тенантов)
        if (Schema::hasTable('admin_users') && !Schema::hasTable('users')) {
            DB::statement('ALTER TABLE admin_users RENAME TO users');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (config('database.default') !== 'pgsql') {
            return;
        }

        // Возвращаем обратно
        if (Schema::hasTable('users')) {
            DB::statement('ALTER TABLE users RENAME TO admin_users');
        }
    }
}

