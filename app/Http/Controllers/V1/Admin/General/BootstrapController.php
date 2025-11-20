<?php

namespace Crater\Http\Controllers\V1\Admin\General;

use Crater\Http\Controllers\Controller;
use Crater\Http\Resources\CompanyResource;
use Crater\Http\Resources\UserResource;
use Crater\Models\Company;
use Crater\Models\CompanySetting;
use Crater\Models\Currency;
use Crater\Models\Module;
use Crater\Models\Setting;
use Crater\Traits\GeneratesMenuTrait;
use Illuminate\Http\Request;
use Silber\Bouncer\BouncerFacade;

class BootstrapController extends Controller
{
    use GeneratesMenuTrait;

    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(Request $request)
    {
        // Логируем для диагностики
        \Log::info('BootstrapController: Request received', [
            'host' => $request->getHost(),
            'session_id' => $request->session()->getId(),
            'has_session' => $request->session()->exists(),
            'auth_guard_web' => \Auth::guard('web')->check(),
            'auth_guard_web_user' => \Auth::guard('web')->user() ? \Auth::guard('web')->user()->id : null,
            'auth_guard_sanctum' => \Auth::guard('sanctum')->check(),
            'auth_guard_sanctum_user' => \Auth::guard('sanctum')->user() ? \Auth::guard('sanctum')->user()->id : null,
            'request_user' => $request->user() ? $request->user()->id : null,
            'tenancy_initialized' => tenancy()->initialized,
            'tenant_id' => tenancy()->initialized ? tenant('id') : null,
        ]);
        
        $current_user = $request->user();
        
        if (!$current_user) {
            \Log::warning('BootstrapController: User not authenticated', [
                'host' => $request->getHost(),
                'session_id' => $request->session()->getId(),
            ]);
            return response()->json(['error' => 'Unauthenticated'], 401);
        }
        
        // Создаем меню перед использованием (если еще не создано)
        if (tenancy()->initialized) {
            $this->ensureMenusCreated();
        }
        
        $current_user_settings = $current_user->getAllSettings();

        $main_menu = $this->generateMenu('main_menu', $current_user);
        \Log::info('BootstrapController: Generated main_menu', [
            'menu_count' => count($main_menu),
            'menu' => $main_menu,
            'tenant_id' => tenancy()->initialized ? tenant('id') : null,
        ]);

        $setting_menu = $this->generateMenu('setting_menu', $current_user);
        \Log::info('BootstrapController: Generated setting_menu', [
            'menu_count' => count($setting_menu),
            'tenant_id' => tenancy()->initialized ? tenant('id') : null,
        ]);

        $companies = $current_user->companies;

        $current_company = Company::find($request->header('company'));

        if ((! $current_company) || ($current_company && ! $current_user->hasCompany($current_company->id))) {
            $current_company = $current_user->companies()->first();
        }

        if (!$current_company) {
            return response()->json(['error' => 'No company found for user'], 500);
        }

        $current_company_settings = CompanySetting::getAllSettings($current_company->id);

        $current_company_currency = $current_company_settings->has('currency')
            ? Currency::find($current_company_settings->get('currency'))
            : Currency::first();

        BouncerFacade::refreshFor($current_user);

        $global_settings = Setting::getSettings([
            'api_token',
            'admin_portal_theme',
            'admin_portal_logo',
            'login_page_logo',
            'login_page_heading',
            'login_page_description',
            'admin_page_title',
            'copyright_text'
        ]);

        return response()->json([
            'current_user' => new UserResource($current_user),
            'current_user_settings' => $current_user_settings,
            'current_user_abilities' => $current_user->getAbilities(),
            'companies' => CompanyResource::collection($companies),
            'current_company' => new CompanyResource($current_company),
            'current_company_settings' => $current_company_settings,
            'current_company_currency' => $current_company_currency,
            'config' => config('crater'),
            'global_settings' => $global_settings,
            'main_menu' => $main_menu,
            'setting_menu' => $setting_menu,
            'modules' => Module::where('enabled', true)->pluck('name'),
        ]);
    }

    /**
     * Ensure menus are created for the current tenant
     */
    protected function ensureMenusCreated()
    {
        // Проверяем, создано ли меню
        $mainMenu = \Menu::get('main_menu');
        if ($mainMenu && $mainMenu->items && $mainMenu->items->count() > 0) {
            return; // Меню уже создано
        }

        // Проверяем условия для создания меню
        $hasDatabaseCreated = \Storage::disk('local')->has('database_created');
        $abilitiesTableName = \Silber\Bouncer\Database\Models::table('abilities');
        $hasAbilitiesTable = \Illuminate\Support\Facades\Schema::hasTable($abilitiesTableName);

        \Log::info('BootstrapController: Ensuring menus are created', [
            'has_database_created' => $hasDatabaseCreated,
            'has_abilities_table' => $hasAbilitiesTable,
            'tenant_id' => tenant('id'),
        ]);

        if ($hasDatabaseCreated && $hasAbilitiesTable) {
            $this->addMenus();
            \Log::info('BootstrapController: Menus created in controller');
        } else {
            \Log::warning('BootstrapController: Cannot create menus', [
                'has_database_created' => $hasDatabaseCreated,
                'has_abilities_table' => $hasAbilitiesTable,
            ]);
        }
    }

    /**
     * Add menus for the tenant
     */
    protected function addMenus()
    {
        //main menu
        \Menu::make('main_menu', function ($menu) {
            foreach (config('crater.main_menu') as $data) {
                $menu->add($data['title'], $data['link'])
                    ->data('icon', $data['icon'])
                    ->data('name', $data['name'])
                    ->data('owner_only', $data['owner_only'])
                    ->data('ability', $data['ability'])
                    ->data('model', $data['model'])
                    ->data('group', $data['group']);
            }
        });

        //setting menu
        \Menu::make('setting_menu', function ($menu) {
            foreach (config('crater.setting_menu') as $data) {
                $menu->add($data['title'], $data['link'])
                    ->data('icon', $data['icon'])
                    ->data('name', $data['name'])
                    ->data('owner_only', $data['owner_only'])
                    ->data('ability', $data['ability'])
                    ->data('model', $data['model'])
                    ->data('group', $data['group']);
            }
        });

        \Menu::make('customer_portal_menu', function ($menu) {
            foreach (config('crater.customer_menu') as $data) {
                $menu->add($data['title'], $data['link'])
                    ->data('icon', $data['icon'])
                    ->data('name', $data['name'])
                    ->data('owner_only', $data['owner_only'])
                    ->data('ability', $data['ability'])
                    ->data('model', $data['model'])
                    ->data('group', $data['group']);
            }
        });
    }
}
