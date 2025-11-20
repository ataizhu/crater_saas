<?php

namespace Crater\Traits;

trait GeneratesMenuTrait
{
    public function generateMenu($key, $user)
    {
        $menu = [];

        $menuObj = \Menu::get($key);
        
        \Log::info("GeneratesMenuTrait: Getting menu '{$key}'", [
            'menu_exists' => $menuObj !== null,
            'has_items' => $menuObj && $menuObj->items ? true : false,
            'items_count' => $menuObj && $menuObj->items ? $menuObj->items->count() : 0,
        ]);
        
        // Проверяем, что меню существует и имеет items
        if (!$menuObj || !$menuObj->items) {
            \Log::warning("GeneratesMenuTrait: Menu '{$key}' not found or empty", [
                'menu_obj' => $menuObj ? 'exists' : 'null',
                'items' => $menuObj && $menuObj->items ? 'exists' : 'null',
            ]);
            return $menu;
        }

        foreach ($menuObj->items->toArray() as $data) {
            if ($user->checkAccess($data)) {
                $menu[] = [
                    'title' => $data->title,
                    'link' => $data->link->path['url'],
                    'icon' => $data->data['icon'],
                    'name' => $data->data['name'],
                    'group' => $data->data['group'],
                ];
            }
        }

        return $menu;
    }
}
