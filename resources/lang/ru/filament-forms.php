<?php

return [

    'fields' => [

        'file_upload' => [
            'editor' => [
                'actions' => [
                    'cancel' => [
                        'label' => 'Отмена',
                    ],
                    'drag_crop' => [
                        'label' => 'Режим перетаскивания "обрезать"',
                    ],
                    'drag_move' => [
                        'label' => 'Режим перетаскивания "переместить"',
                    ],
                    'flip_horizontal' => [
                        'label' => 'Отразить изображение по горизонтали',
                    ],
                    'flip_vertical' => [
                        'label' => 'Отразить изображение по вертикали',
                    ],
                    'move_down' => [
                        'label' => 'Переместить изображение вниз',
                    ],
                    'move_left' => [
                        'label' => 'Переместить изображение влево',
                    ],
                    'move_right' => [
                        'label' => 'Переместить изображение вправо',
                    ],
                    'move_up' => [
                        'label' => 'Переместить изображение вверх',
                    ],
                    'reset' => [
                        'label' => 'Сброс',
                    ],
                    'rotate_left' => [
                        'label' => 'Повернуть изображение влево',
                    ],
                    'rotate_right' => [
                        'label' => 'Повернуть изображение вправо',
                    ],
                    'save' => [
                        'label' => 'Сохранить',
                    ],
                    'zoom_100' => [
                        'label' => 'Увеличить изображение до 100%',
                    ],
                    'zoom_in' => [
                        'label' => 'Увеличить',
                    ],
                    'zoom_out' => [
                        'label' => 'Уменьшить',
                    ],
                ],
            ],
        ],

        'key_value' => [
            'actions' => [
                'add' => [
                    'label' => 'Добавить строку',
                ],
                'delete' => [
                    'label' => 'Удалить строку',
                ],
            ],
            'fields' => [
                'key' => [
                    'label' => 'Ключ',
                ],
                'value' => [
                    'label' => 'Значение',
                ],
            ],
        ],

        'repeater' => [
            'actions' => [
                'add' => [
                    'label' => 'Добавить в :label',
                ],
                'delete' => [
                    'label' => 'Удалить',
                ],
                'move_down' => [
                    'label' => 'Переместить вниз',
                ],
                'move_up' => [
                    'label' => 'Переместить вверх',
                ],
            ],
        ],

        'rich_editor' => [
            'dialogs' => [
                'link' => [
                    'actions' => [
                        'link' => 'Ссылка',
                        'unlink' => 'Удалить ссылку',
                    ],
                    'label' => 'URL',
                    'placeholder' => 'Введите URL',
                ],
            ],
            'toolbar_buttons' => [
                'attach_files' => 'Прикрепить файлы',
                'blockquote' => 'Цитата',
                'bold' => 'Жирный',
                'bullet_list' => 'Маркированный список',
                'code_block' => 'Блок кода',
                'h1' => 'Заголовок',
                'h2' => 'Подзаголовок',
                'h3' => 'Подзаголовок 3',
                'italic' => 'Курсив',
                'link' => 'Ссылка',
                'ordered_list' => 'Нумерованный список',
                'redo' => 'Повторить',
                'strike' => 'Зачеркнутый',
                'undo' => 'Отменить',
            ],
        ],

        'select' => [
            'actions' => [
                'create' => [
                    'label' => 'Создать',
                ],
            ],
            'no_search_results_message' => 'Ничего не найдено.',
            'placeholder' => 'Выберите опцию',
            'searching_message' => 'Поиск...',
            'search_prompt' => 'Начните вводить для поиска...',
        ],

        'tags_input' => [
            'placeholder' => 'Новый тег',
        ],

    ],

    'messages' => [
        'unsaved_changes' => 'Есть несохраненные изменения. Вы уверены, что хотите покинуть страницу?',
    ],

];

