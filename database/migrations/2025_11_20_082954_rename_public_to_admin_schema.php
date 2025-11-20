<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RenamePublicToAdminSchema extends Migration
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

        $databaseName = DB::connection()->getDatabaseName();
        $username = config('database.connections.pgsql.username', 'crater');

        // Проверяем существование схем
        $adminCount = DB::selectOne("SELECT COUNT(*) as count FROM information_schema.schemata WHERE schema_name = 'admin'");
        $publicCount = DB::selectOne("SELECT COUNT(*) as count FROM information_schema.schemata WHERE schema_name = 'public'");

        // Переименовываем public в admin, если нужно
        if ($adminCount->count == 0 && $publicCount->count > 0) {
            DB::statement('ALTER SCHEMA public RENAME TO admin');
        }

        // Создаем схему admin, если её нет
        if ($adminCount->count == 0) {
            DB::statement('CREATE SCHEMA IF NOT EXISTS admin');
            DB::statement("GRANT ALL ON SCHEMA admin TO {$username}");
        }

        // Создаем public схему обратно (PostgreSQL требует)
        DB::statement('CREATE SCHEMA IF NOT EXISTS public');
        DB::statement("GRANT ALL ON SCHEMA public TO {$username}");

        // Обновляем search_path
        DB::statement("ALTER DATABASE {$databaseName} SET search_path TO admin, public");

        // Переключаемся на схему admin для создания таблиц
        DB::statement('SET search_path TO admin');

        // Создаем таблицу tenants
        if (!Schema::hasTable('tenants')) {
            Schema::create('tenants', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('name')->nullable();
                $table->string('owner_name')->nullable();
                $table->string('owner_email')->nullable();
                $table->string('owner_password')->nullable();
                $table->timestamps();
                $table->json('data')->nullable();
            });
        }

        // Создаем таблицу domains
        if (!Schema::hasTable('domains')) {
            Schema::create('domains', function (Blueprint $table) {
                $table->increments('id');
                $table->string('domain', 255)->unique();
                $table->string('tenant_id');
                $table->timestamps();
                $table->foreign('tenant_id')->references('id')->on('tenants')->onUpdate('cascade')->onDelete('cascade');
            });
        }

        // Создаем таблицу admin_users
        if (!Schema::hasTable('admin_users')) {
            Schema::create('admin_users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
                $table->rememberToken();
                $table->timestamps();
            });
        }

        // Создаем таблицы cache
        if (!Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration');
            });
        }

        if (!Schema::hasTable('cache_locks')) {
            Schema::create('cache_locks', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->string('owner');
                $table->integer('expiration');
            });
        }

        // Создаем таблицу personal_access_tokens (если нужна)
        if (!Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->morphs('tokenable');
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        // Удаляем лишние таблицы из admin схемы (таблицы тенантов)
        $tenantTables = [
            'abilities', 'addresses', 'assigned_roles', 'companies', 'company_settings',
            'countries', 'currencies', 'custom_field_values', 'custom_fields', 'customers',
            'email_logs', 'estimate_items', 'estimates', 'exchange_rate_logs',
            'exchange_rate_providers', 'expense_categories', 'expenses', 'file_disks',
            'invoice_items', 'invoices', 'items', 'media', 'modules', 'notes',
            'notifications', 'password_resets', 'payment_methods', 'payments',
            'permissions', 'recurring_invoices', 'roles', 'settings', 'tax_types',
            'taxes', 'transactions', 'units', 'user_company', 'user_settings', 'users'
        ];

        foreach ($tenantTables as $table) {
            if (Schema::hasTable($table)) {
                Schema::dropIfExists($table);
            }
        }

        // Удаляем старые записи о миграциях админки из таблицы migrations
        // (так как теперь все в одной миграции)
        if (Schema::hasTable('migrations')) {
            DB::table('migrations')
                ->whereIn('migration', [
                    '2019_09_15_000010_create_tenants_table',
                    '2019_09_15_000020_create_domains_table',
                    '2025_10_26_121223_create_admin_users_table',
                    '2025_11_10_061713_create_cache_table',
                    '2025_11_20_072712_rename_public_schema_to_admin_and_cleanup_central_tables'
                ])
                ->delete();
            
            // Также удаляем записи о tenant миграциях, которые попали в admin схему
            DB::table('migrations')
                ->where('migration', 'NOT LIKE', '%tenants%')
                ->where('migration', 'NOT LIKE', '%domains%')
                ->where('migration', 'NOT LIKE', '%admin_users%')
                ->where('migration', 'NOT LIKE', '%cache%')
                ->where('migration', 'NOT LIKE', '%personal_access_tokens%')
                ->where('migration', 'LIKE', '%create_%')
                ->delete();
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

        $databaseName = DB::connection()->getDatabaseName();
        DB::statement('ALTER SCHEMA admin RENAME TO public');
        DB::statement("ALTER DATABASE {$databaseName} SET search_path TO public");
    }
}
