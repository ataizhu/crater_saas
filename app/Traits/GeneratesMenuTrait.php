<?php

namespace Crater\Traits;

trait GeneratesMenuTrait
{
    public function generateMenu($key, $user)
    {
        $menu = [];

        $menuObj = \Menu::get($key);
        
        // Проверяем, что меню существует и имеет items
        if (!$menuObj || !$menuObj->items) {
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
