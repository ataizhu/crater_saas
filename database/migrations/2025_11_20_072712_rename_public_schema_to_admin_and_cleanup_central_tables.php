<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RenamePublicSchemaToAdminAndCleanupCentralTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Only for PostgreSQL
        if (config('database.default') !== 'pgsql') {
            return;
        }

        $connection = DB::connection();
        $databaseName = $connection->getDatabaseName();

        // Check if admin schema already exists
        $adminExists = DB::selectOne("
            SELECT COUNT(*) as count
            FROM information_schema.schemata 
            WHERE schema_name = 'admin'
        ");
        
        $adminExists = $adminExists && $adminExists->count > 0;

        // Check if public schema still exists
        $publicExists = DB::selectOne("
            SELECT COUNT(*) as count
            FROM information_schema.schemata 
            WHERE schema_name = 'public'
        ");
        
        $publicExists = $publicExists && $publicExists->count > 0;

        // Rename public schema to admin only if:
        // 1. Admin schema doesn't exist
        // 2. Public schema still exists
        if (!$adminExists && $publicExists) {
            try {
                DB::statement('ALTER SCHEMA public RENAME TO admin');
            } catch (\Exception $e) {
                // Schema might already be renamed, continue
                \Log::warning("Could not rename public schema: " . $e->getMessage());
            }
        }

        // Create public schema if it doesn't exist (PostgreSQL requires it)
        DB::statement('CREATE SCHEMA IF NOT EXISTS public');
        DB::statement("GRANT ALL ON SCHEMA public TO " . config('database.connections.pgsql.username'));

        // Update search_path for the database
        DB::statement("ALTER DATABASE {$databaseName} SET search_path TO admin, public");

        // List of tenant tables that should NOT be in central admin schema
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

        // Drop tenant tables from admin schema if they exist
        foreach ($tenantTables as $table) {
            $tableExists = DB::selectOne("
                SELECT EXISTS(
                    SELECT 1 
                    FROM information_schema.tables 
                    WHERE table_schema = 'admin' 
                    AND table_name = ?
                ) as exists
            ", [$table]);

            if ($tableExists && $tableExists->exists) {
                DB::statement("DROP TABLE IF EXISTS admin.{$table} CASCADE");
            }
        }

        // Clean up migrations table - remove tenant migration records
        if (Schema::hasTable('migrations')) {
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
        // Only for PostgreSQL
        if (config('database.default') !== 'pgsql') {
            return;
        }

        $databaseName = DB::connection()->getDatabaseName();

        // Rename admin schema back to public
        DB::statement('ALTER SCHEMA admin RENAME TO public');

        // Reset search_path
        DB::statement("ALTER DATABASE {$databaseName} SET search_path TO public");
    }
}
