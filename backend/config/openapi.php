<?php

return [
    'openapi' => '3.0.3',
    'info' => [
        'title' => 'Films API',
        'version' => '1.0.0',
        'description' => 'Документация для API backend.',
    ],
    'tags' => [
        [
            'name' => 'Auth',
            'description' => 'Аутентификация',
        ],
        [
            'name' => 'Movies',
            'description' => 'Работа с фильмами',
        ],
    ],
    'components' => [
        'securitySchemes' => [
            'bearerAuth' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'JWT',
            ],
        ],
        'schemas' => [
            'AuthRequest' => [
                'type' => 'object',
                'properties' => [
                    'email' => ['type' => 'string', 'format' => 'email'],
                    'password' => ['type' => 'string', 'format' => 'password'],
                ],
                'required' => ['email', 'password'],
            ],
            'AuthResponse' => [
                'type' => 'object',
                'properties' => [
                    'token' => ['type' => 'string'],
                    'email' => ['type' => 'string', 'format' => 'email'],
                ],
                'required' => ['token', 'email'],
            ],
            'RegisterRequest' => [
                'type' => 'object',
                'properties' => [
                    'username' => ['type' => 'string', 'minLength' => 3],
                    'email' => ['type' => 'string', 'format' => 'email'],
                    'password' => ['type' => 'string', 'minLength' => 6],
                ],
                'required' => ['username', 'email', 'password'],
            ],
            'RegisterResponse' => [
                'type' => 'object',
                'properties' => [
                    'message' => ['type' => 'string'],
                    'email' => ['type' => 'string', 'format' => 'email'],
                ],
                'required' => ['message', 'email'],
            ],
            'MeResponse' => [
                'type' => 'object',
                'properties' => [
                    'email' => ['type' => 'string', 'format' => 'email'],
                ],
                'required' => ['email'],
            ],
            'Movie' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string'],
                    'list' => [
                        'type' => 'string',
                        'enum' => ['my', 'later', 'deleted'],
                    ],
                    'title' => ['type' => 'string'],
                    'year' => ['type' => 'integer', 'nullable' => true],
                    'runtimeMin' => ['type' => 'integer', 'nullable' => true],
                    'genresCsv' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'notes' => ['type' => 'string'],
                    'watched' => ['type' => 'boolean'],
                    'rating' => ['type' => 'integer', 'nullable' => true],
                    'watchedAt' => ['type' => 'string', 'nullable' => true],
                    'posterUrl' => ['type' => 'string', 'nullable' => true],
                    'url' => ['type' => 'string', 'nullable' => true],
                    'addedAt' => ['type' => 'string', 'format' => 'date-time'],
                ],
                'required' => ['id', 'list', 'title', 'watched', 'genresCsv', 'description', 'notes', 'addedAt'],
            ],
            'MovieCreateInput' => [
                'type' => 'object',
                'properties' => [
                    'list' => [
                        'type' => 'string',
                        'enum' => ['my', 'later'],
                    ],
                    'title' => ['type' => 'string'],
                    'year' => ['type' => 'integer', 'nullable' => true],
                    'runtimeMin' => ['type' => 'integer', 'nullable' => true],
                    'genresCsv' => ['type' => 'string', 'nullable' => true],
                    'description' => ['type' => 'string', 'nullable' => true],
                    'notes' => ['type' => 'string', 'nullable' => true],
                    'watched' => ['type' => 'boolean'],
                    'rating' => ['type' => 'integer', 'nullable' => true],
                    'watchedAt' => ['type' => 'string', 'nullable' => true],
                    'url' => ['type' => 'string', 'nullable' => true],
                    'removePoster' => ['type' => 'string', 'enum' => ['1', '0'], 'nullable' => true],
                    'poster' => ['type' => 'string', 'format' => 'binary', 'nullable' => true],
                ],
                'required' => ['list', 'title'],
            ],
            'MovieUpdateInput' => [
                'type' => 'object',
                'properties' => [
                    'list' => [
                        'type' => 'string',
                        'enum' => ['my', 'later', 'deleted'],
                    ],
                    'title' => ['type' => 'string'],
                    'year' => ['type' => 'integer', 'nullable' => true],
                    'runtimeMin' => ['type' => 'integer', 'nullable' => true],
                    'genresCsv' => ['type' => 'string', 'nullable' => true],
                    'description' => ['type' => 'string', 'nullable' => true],
                    'notes' => ['type' => 'string', 'nullable' => true],
                    'watched' => ['type' => 'boolean'],
                    'rating' => ['type' => 'integer', 'nullable' => true],
                    'watchedAt' => ['type' => 'string', 'nullable' => true],
                    'url' => ['type' => 'string', 'nullable' => true],
                    'removePoster' => ['type' => 'string', 'enum' => ['1', '0'], 'nullable' => true],
                    'poster' => ['type' => 'string', 'format' => 'binary', 'nullable' => true],
                ],
            ],
            'MovieListResponse' => [
                'type' => 'object',
                'properties' => [
                    'items' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/Movie'],
                    ],
                    'total' => ['type' => 'integer'],
                ],
                'required' => ['items', 'total'],
            ],
            'DuplicateMovie' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string'],
                    'title' => ['type' => 'string'],
                    'year' => ['type' => 'integer', 'nullable' => true],
                    'list' => ['type' => 'string'],
                ],
                'required' => ['id', 'title', 'list'],
            ],
            'ValidationErrors' => [
                'type' => 'object',
                'properties' => [
                    'errors' => [
                        'type' => 'object',
                        'additionalProperties' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'paths' => [
        '/api/auth/login' => [
            'post' => [
                'tags' => ['Auth'],
                'summary' => 'Авторизация пользователя',
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/AuthRequest'],
                        ],
                    ],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Успешный вход',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/AuthResponse'],
                            ],
                        ],
                    ],
                    '401' => [
                        'description' => 'Неверные учетные данные',
                    ],
                    '422' => [
                        'description' => 'Ошибки валидации',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/ValidationErrors'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        '/api/auth/register' => [
            'post' => [
                'tags' => ['Auth'],
                'summary' => 'Регистрация пользователя',
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/RegisterRequest'],
                        ],
                    ],
                ],
                'responses' => [
                    '201' => [
                        'description' => 'Регистрация успешна',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/RegisterResponse'],
                            ],
                        ],
                    ],
                    '400' => [
                        'description' => 'Некорректные данные',
                    ],
                    '409' => [
                        'description' => 'Конфликт данных',
                    ],
                ],
            ],
        ],
        '/api/me' => [
            'get' => [
                'tags' => ['Auth'],
                'summary' => 'Получить данные текущего пользователя',
                'security' => [
                    ['bearerAuth' => []],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Пользователь',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/MeResponse'],
                            ],
                        ],
                    ],
                    '401' => [
                        'description' => 'Не авторизован',
                    ],
                ],
            ],
        ],
        '/api/movies' => [
            'get' => [
                'tags' => ['Movies'],
                'summary' => 'Список фильмов',
                'security' => [
                    ['bearerAuth' => []],
                ],
                'parameters' => [
                    [
                        'name' => 'list',
                        'in' => 'query',
                        'schema' => ['type' => 'string', 'enum' => ['my', 'later', 'deleted']],
                    ],
                    [
                        'name' => 'page',
                        'in' => 'query',
                        'schema' => ['type' => 'integer', 'default' => 1],
                    ],
                    [
                        'name' => 'pageSize',
                        'in' => 'query',
                        'schema' => ['type' => 'integer', 'default' => 12],
                    ],
                    [
                        'name' => 'sort',
                        'in' => 'query',
                        'schema' => [
                            'type' => 'string',
                            'enum' => [
                                'added_desc',
                                'added_asc',
                                'title_asc',
                                'title_desc',
                                'rating_desc',
                                'rating_asc',
                                'year_desc',
                                'year_asc',
                                'watched_at_desc',
                                'watched_at_asc',
                                'deleted_at_desc',
                                'deleted_at_asc',
                            ],
                        ],
                    ],
                    [
                        'name' => 'q',
                        'in' => 'query',
                        'schema' => ['type' => 'string'],
                    ],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Список фильмов',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/MovieListResponse'],
                            ],
                        ],
                    ],
                ],
            ],
            'post' => [
                'tags' => ['Movies'],
                'summary' => 'Создать фильм',
                'security' => [
                    ['bearerAuth' => []],
                ],
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/MovieCreateInput'],
                        ],
                        'multipart/form-data' => [
                            'schema' => ['$ref' => '#/components/schemas/MovieCreateInput'],
                        ],
                    ],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Созданный фильм',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'movie' => ['$ref' => '#/components/schemas/Movie'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '422' => [
                        'description' => 'Ошибки валидации',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/ValidationErrors'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        '/api/movies/export.csv' => [
            'get' => [
                'tags' => ['Movies'],
                'summary' => 'Экспорт фильмов в CSV',
                'security' => [
                    ['bearerAuth' => []],
                ],
                'parameters' => [
                    [
                        'name' => 'scope',
                        'in' => 'query',
                        'schema' => ['type' => 'string', 'enum' => ['all', 'my', 'later', 'deleted']],
                    ],
                    [
                        'name' => 'q',
                        'in' => 'query',
                        'schema' => ['type' => 'string'],
                    ],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'CSV файл',
                        'content' => [
                            'text/csv' => [
                                'schema' => ['type' => 'string', 'format' => 'binary'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        '/api/movies/{id}' => [
            'get' => [
                'tags' => ['Movies'],
                'summary' => 'Получить фильм',
                'security' => [
                    ['bearerAuth' => []],
                ],
                'parameters' => [
                    [
                        'name' => 'id',
                        'in' => 'path',
                        'required' => true,
                        'schema' => ['type' => 'string'],
                    ],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Данные фильма',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'movie' => ['$ref' => '#/components/schemas/Movie'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '404' => [
                        'description' => 'Фильм не найден',
                    ],
                ],
            ],
            'patch' => [
                'tags' => ['Movies'],
                'summary' => 'Обновить фильм',
                'security' => [
                    ['bearerAuth' => []],
                ],
                'parameters' => [
                    [
                        'name' => 'id',
                        'in' => 'path',
                        'required' => true,
                        'schema' => ['type' => 'string'],
                    ],
                ],
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/MovieUpdateInput'],
                        ],
                        'multipart/form-data' => [
                            'schema' => ['$ref' => '#/components/schemas/MovieUpdateInput'],
                        ],
                    ],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Обновленный фильм',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'movie' => ['$ref' => '#/components/schemas/Movie'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '422' => [
                        'description' => 'Ошибки валидации',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/ValidationErrors'],
                            ],
                        ],
                    ],
                ],
            ],
            'post' => [
                'tags' => ['Movies'],
                'summary' => 'Обновить фильм (POST)',
                'security' => [
                    ['bearerAuth' => []],
                ],
                'parameters' => [
                    [
                        'name' => 'id',
                        'in' => 'path',
                        'required' => true,
                        'schema' => ['type' => 'string'],
                    ],
                ],
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/MovieUpdateInput'],
                        ],
                        'multipart/form-data' => [
                            'schema' => ['$ref' => '#/components/schemas/MovieUpdateInput'],
                        ],
                    ],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Обновленный фильм',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'movie' => ['$ref' => '#/components/schemas/Movie'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '422' => [
                        'description' => 'Ошибки валидации',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/ValidationErrors'],
                            ],
                        ],
                    ],
                ],
            ],
            'delete' => [
                'tags' => ['Movies'],
                'summary' => 'Удалить фильм',
                'security' => [
                    ['bearerAuth' => []],
                ],
                'parameters' => [
                    [
                        'name' => 'id',
                        'in' => 'path',
                        'required' => true,
                        'schema' => ['type' => 'string'],
                    ],
                    [
                        'name' => 'hard',
                        'in' => 'query',
                        'schema' => ['type' => 'string', 'enum' => ['1']],
                    ],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Операция выполнена',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'ok' => ['type' => 'boolean'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        '/api/movies/{id}/move' => [
            'post' => [
                'tags' => ['Movies'],
                'summary' => 'Переместить фильм между списками',
                'security' => [
                    ['bearerAuth' => []],
                ],
                'parameters' => [
                    [
                        'name' => 'id',
                        'in' => 'path',
                        'required' => true,
                        'schema' => ['type' => 'string'],
                    ],
                ],
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'toList' => ['type' => 'string', 'enum' => ['my', 'later']],
                                ],
                                'required' => ['toList'],
                            ],
                        ],
                    ],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Операция выполнена',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'ok' => ['type' => 'boolean'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        '/api/movies/{id}/restore' => [
            'post' => [
                'tags' => ['Movies'],
                'summary' => 'Восстановить фильм из удаленных',
                'security' => [
                    ['bearerAuth' => []],
                ],
                'parameters' => [
                    [
                        'name' => 'id',
                        'in' => 'path',
                        'required' => true,
                        'schema' => ['type' => 'string'],
                    ],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Операция выполнена',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'ok' => ['type' => 'boolean'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '409' => [
                        'description' => 'Найдены дубликаты',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'duplicates' => [
                                            'type' => 'array',
                                            'items' => ['$ref' => '#/components/schemas/DuplicateMovie'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        '/api/movies/duplicates/check' => [
            'post' => [
                'tags' => ['Movies'],
                'summary' => 'Проверить дубликаты по названию',
                'security' => [
                    ['bearerAuth' => []],
                ],
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'title' => ['type' => 'string'],
                                    'year' => ['type' => 'integer', 'nullable' => true],
                                    'excludeId' => ['type' => 'string', 'nullable' => true],
                                ],
                                'required' => ['title'],
                            ],
                        ],
                    ],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Список дубликатов',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'duplicates' => [
                                            'type' => 'array',
                                            'items' => ['$ref' => '#/components/schemas/DuplicateMovie'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
