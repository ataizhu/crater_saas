<?php

return [
    'pages' => [
        'dashboard' => [
            'title' => 'Панель управления',
        ],
    ],
    
    'resources' => [
        'tenant' => [
            'label' => 'Клиент',
            'plural_label' => 'Клиенты',
            'navigation_label' => 'Клиенты',
            
            'fields' => [
                'url' => 'URL клиента',
                'url_hint' => 'Будет создан после сохранения',
                'subdomain' => 'Субдомен',
                'subdomain_hint' => 'Будет доступен на: subdomain.crater.test',
                'company_name' => 'Название компании',
                'owner_name' => 'Имя владельца',
                'owner_email' => 'Email владельца',
                'owner_password' => 'Пароль владельца',
                'owner_password_hint' => 'Оставьте пустым чтобы сохранить текущий пароль при редактировании.',
                'created' => 'Создан',
                'owner' => 'Владелец',
                'email' => 'Email',
            ],
            
            'actions' => [
                'visit_site' => 'Открыть сайт',
            ],
            
            'notifications' => [
                'created_title' => 'Клиент создан и инициализирован!',
                'created_body' => "Клиент готов!\n\nДля локальной разработки добавьте в /etc/hosts:\n127.0.0.1 {subdomain}.crater.test\n\nЗатем откройте: {url}",
                'failed_title' => 'Клиент создан, но инициализация не удалась!',
                'failed_body' => "Запустите вручную: php artisan tenant:initialize {id}\n\nОшибка: {error}",
            ],
        ],
    ],
    
    'forms' => [
        'actions' => [
            'submit' => [
                'label' => 'Сохранить',
            ],
            'cancel' => [
                'label' => 'Отмена',
            ],
        ],
    ],
    
    'tables' => [
        'filters' => [
            'buttons' => [
                'reset' => [
                    'label' => 'Сбросить фильтры',
                ],
            ],
        ],
        'actions' => [
            'edit' => [
                'label' => 'Редактировать',
            ],
            'delete' => [
                'label' => 'Удалить',
            ],
            'view' => [
                'label' => 'Просмотр',
            ],
        ],
        'bulk_actions' => [
            'delete' => [
                'label' => 'Удалить выбранные',
                'confirmation' => [
                    'heading' => 'Удалить выбранные записи?',
                    'body' => 'Вы уверены что хотите удалить выбранные записи? Это действие необратимо.',
                ],
            ],
        ],
        'pagination' => [
            'label' => 'Навигация по страницам',
            'overview' => 'Показано с {first} по {last} из {total} записей',
            'buttons' => [
                'go_to_page' => [
                    'label' => 'Перейти на страницу {page}',
                ],
                'next' => [
                    'label' => 'Следующая',
                ],
                'previous' => [
                    'label' => 'Предыдущая',
                ],
            ],
        ],
        'empty' => [
            'heading' => 'Нет записей',
        ],
    ],
    
    'auth' => [
        'login' => [
            'heading' => 'Вход в систему',
            'form' => [
                'email' => [
                    'label' => 'Email',
                ],
                'password' => [
                    'label' => 'Пароль',
                ],
                'remember' => [
                    'label' => 'Запомнить меня',
                ],
                'submit' => [
                    'label' => 'Войти',
                ],
            ],
            'messages' => [
                'failed' => 'Неверный email или пароль.',
            ],
        ],
        'logout' => [
            'label' => 'Выйти',
        ],
    ],
    
    'notifications' => [
        'title' => 'Уведомления',
    ],
    
    'user_menu' => [
        'account' => [
            'label' => 'Аккаунт',
        ],
        'logout' => [
            'label' => 'Выйти',
        ],
    ],
];

