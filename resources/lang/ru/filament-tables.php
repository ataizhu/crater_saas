<?php

return [

    'actions' => [

        'attach' => [
            'label' => 'Прикрепить существующее',
        ],

        'bulk_actions' => [
            'label' => 'Массовые действия',
        ],

        'delete' => [
            'label' => 'Удалить',
        ],

        'detach' => [
            'label' => 'Открепить',
        ],

        'edit' => [
            'label' => 'Редактировать',
        ],

        'filter' => [
            'label' => 'Фильтр',
        ],

        'open' => [
            'label' => 'Открыть',
        ],

        'replicate' => [
            'label' => 'Дублировать',
        ],

        'view' => [
            'label' => 'Просмотр',
        ],

    ],

    'bulk_actions' => [

        'delete' => [
            'confirmation' => [
                'body' => 'Вы уверены, что хотите удалить выбранные записи? Это действие необратимо.',
                'heading' => 'Удалить выбранные записи?',
            ],
            'label' => 'Удалить выбранные',
        ],

        'detach' => [
            'confirmation' => [
                'body' => 'Вы уверены, что хотите открепить выбранные записи?',
                'heading' => 'Открепить выбранные записи?',
            ],
            'label' => 'Открепить выбранные',
        ],

    ],

    'columns' => [

        'checks' => [
            'boolean' => [
                'true' => 'Да',
                'false' => 'Нет',
            ],
        ],

    ],

    'fields' => [

        'select' => [
            'no_options_message' => 'Начните вводить для поиска...',
            'no_search_results_message' => 'Ничего не найдено.',
            'search_placeholder' => 'Поиск...',
        ],

    ],

    'filters' => [

        'buttons' => [

            'remove' => [
                'label' => 'Удалить фильтр',
            ],

            'remove_all' => [
                'label' => 'Удалить все фильтры',
                'tooltip' => 'Удалить все фильтры',
            ],

            'reset' => [
                'label' => 'Сбросить',
            ],

        ],

        'indicator' => 'Активные фильтры',

        'multi_select' => [
            'placeholder' => 'Все',
        ],

        'select' => [
            'placeholder' => 'Все',
        ],

        'trashed' => [

            'label' => 'Удаленные записи',

            'only_trashed' => 'Только удаленные записи',

            'with_trashed' => 'С удаленными записями',

            'without_trashed' => 'Без удаленных записей',

        ],

    ],

    'pagination' => [

        'fields' => [

            'records_per_page' => [
                'label' => 'на странице',
            ],

        ],

        'label' => 'Навигация по страницам',

        'overview' => 'Показано с :first по :last из :total записей',

        'buttons' => [

            'go_to_page' => [
                'label' => 'Перейти на страницу :page',
            ],

            'next' => [
                'label' => 'Следующая',
            ],

            'previous' => [
                'label' => 'Предыдущая',
            ],

        ],

    ],

    'selection_indicator' => [

        'selected_count' => '{1} Выбрана 1 запись|[2,4] Выбрано :count записи|[5,*] Выбрано :count записей',

        'buttons' => [

            'deselect_all' => [
                'label' => 'Снять выделение со всех',
            ],

            'select_all' => [
                'label' => 'Выбрать все :count',
            ],

        ],

    ],

];

