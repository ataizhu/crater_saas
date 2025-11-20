<?php

namespace Crater\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Paginator::useBootstrapThree();
        $this->loadJsonTranslationsFrom(resource_path('scripts/locales'));

        // Выполняем только для тенантов, не для центрального домена
        if (tenancy()->initialized) {
            // Для тенантов используем Storage, который автоматически использует tenant-scoped disk
            $hasDatabaseCreated = \Storage::disk('local')->has('database_created');
            
            // Проверяем таблицу abilities через Bouncer Models, так как она может использовать префикс
            $abilitiesTableName = \Silber\Bouncer\Database\Models::table('abilities');
            $hasAbilitiesTable = Schema::hasTable($abilitiesTableName);
            
            \Log::info('AppServiceProvider: Checking menu creation', [
                'has_database_created' => $hasDatabaseCreated,
                'abilities_table_name' => $abilitiesTableName,
                'has_abilities_table' => $hasAbilitiesTable,
                'tenant_id' => tenant('id'),
            ]);
            
            // Если таблица abilities существует, но файл database_created отсутствует,
            // создаем его (тенант был инициализирован, но файл потерян)
            if ($hasAbilitiesTable && !$hasDatabaseCreated) {
                \Log::info('AppServiceProvider: Creating missing database_created file');
                \Storage::disk('local')->put('database_created', now());
                $hasDatabaseCreated = true;
            }
            
            if ($hasDatabaseCreated && $hasAbilitiesTable) {
                $this->addMenus();
                \Log::info('AppServiceProvider: Menus created successfully');
            } else {
                \Log::warning('AppServiceProvider: Menus not created', [
                    'has_database_created' => $hasDatabaseCreated,
                    'abilities_table_name' => $abilitiesTableName,
                    'has_abilities_table' => $hasAbilitiesTable,
                ]);
            }
        }
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    public function addMenus()
    {
        //main menu
        \Menu::make('main_menu', function ($menu) {
            foreach (config('crater.main_menu') as $data) {
                $this->generateMenu($menu, $data);
            }
        });

        //setting menu
        \Menu::make('setting_menu', function ($menu) {
            foreach (config('crater.setting_menu') as $data) {
                $this->generateMenu($menu, $data);
            }
        });

        \Menu::make('customer_portal_menu', function ($menu) {
            foreach (config('crater.customer_menu') as $data) {
                $this->generateMenu($menu, $data);
            }
        });
    }

    public function generateMenu($menu, $data)
    {
        $menu->add($data['title'], $data['link'])
            ->data('icon', $data['icon'])
            ->data('name', $data['name'])
            ->data('owner_only', $data['owner_only'])
            ->data('ability', $data['ability'])
            ->data('model', $data['model'])
            ->data('group', $data['group']);
    }
}
