<?php

return [
    'notify' => [
        // Emai на который отправляется уведомление
        'to' => "",
        // Поле From для письма
        'from' => 'Мониторинг файлов <noreply@domain.com>'
    ],
    'scan' => [
        [
            // Полный путь к папке
            'dir'        => '',
            // Перечень расширений файлов которые нужно проверить
            'extensions' => 'php,htaccess',
        ],
    ]
];
