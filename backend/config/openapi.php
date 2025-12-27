<?php

return [
    'openapi' => '3.0.3',
    'info' => [
        'title' => 'Backend API',
        'version' => '1.0.0',
        'description' => 'Документация для методов backend-контроллеров.',
    ],
    'tags' => [
        [
            'name' => 'Site',
            'description' => 'Методы сайта',
        ],
    ],
    'paths' => [
        '/site/index' => [
            'get' => [
                'tags' => ['Site'],
                'summary' => 'Главная страница',
                'responses' => [
                    '200' => [
                        'description' => 'HTML страница',
                    ],
                ],
            ],
        ],
        '/site/login' => [
            'get' => [
                'tags' => ['Site'],
                'summary' => 'Форма входа',
                'responses' => [
                    '200' => [
                        'description' => 'HTML форма',
                    ],
                ],
            ],
            'post' => [
                'tags' => ['Site'],
                'summary' => 'Авторизация пользователя',
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/x-www-form-urlencoded' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'LoginForm[username]' => [
                                        'type' => 'string',
                                        'example' => 'admin',
                                    ],
                                    'LoginForm[password]' => [
                                        'type' => 'string',
                                        'format' => 'password',
                                    ],
                                    'LoginForm[rememberMe]' => [
                                        'type' => 'boolean',
                                    ],
                                ],
                                'required' => ['LoginForm[username]', 'LoginForm[password]'],
                            ],
                        ],
                    ],
                ],
                'responses' => [
                    '302' => [
                        'description' => 'Редирект после успешного входа',
                    ],
                    '200' => [
                        'description' => 'Форма с ошибками валидации',
                    ],
                ],
            ],
        ],
        '/site/logout' => [
            'post' => [
                'tags' => ['Site'],
                'summary' => 'Выход из системы',
                'responses' => [
                    '302' => [
                        'description' => 'Редирект на главную страницу',
                    ],
                ],
            ],
        ],
    ],
];
