<?php
$params = array_merge(
    require __DIR__ . '/../../common/config/params.php',
    require __DIR__ . '/../../common/config/params-local.php',
    require __DIR__ . '/params.php',
    require __DIR__ . '/params-local.php'
);

return [
    'id' => 'app-backend',
    'basePath' => dirname(__DIR__),
    'controllerNamespace' => 'backend\controllers',
    'bootstrap' => ['log'],
    'modules' => [],
    'components' => [
        'request' => [
            'csrfParam' => '_csrf-backend',
            'parsers' => [
                'application/json' => \yii\web\JsonParser::class,
            ],
            'trustedHosts' => [
                '172.27.0.1' => [
                    'X-Forwarded-For',
                    'X-Forwarded-Proto',
                    'X-Forwarded-Host',
                ],
            ],
        ],
        'user' => [
            'identityClass' => 'common\models\User',
            'enableAutoLogin' => false,
            'enableSession' => false,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => \yii\log\FileTarget::class,
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'errorHandler' => [
            'errorAction' => null,
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
                'POST api/auth/login' => 'api-auth/login',
                'POST api/auth/register' => 'api-auth/register',
                'POST api/auth/password' => 'api-auth/password',
                'GET api/me' => 'api-auth/me',
                'GET api/profile' => 'api-profile/view',
                'PATCH api/profile' => 'api-profile/update',
                'POST api/profile' => 'api-profile/update',
                'PATCH api/profile/security' => 'api-profile/security',
                'GET api/movies' => 'api-movies/index',
                'GET api/movies/filters' => 'api-movies/filters',
                'GET api/movies/export.csv' => 'api-movies/export',
                'GET api/movies/<id>' => 'api-movies/view',
                'POST api/movies' => 'api-movies/create',
                'POST api/movies/import/kinopoisk' => 'api-movies/import-kinopoisk',
                'POST api/movies/copy' => 'api-movies/copy',
                'PATCH api/movies/<id>' => 'api-movies/update',
                'POST api/movies/<id>' => 'api-movies/update',
                'DELETE api/movies/<id>' => 'api-movies/delete',
                'POST api/movies/<id>/move' => 'api-movies/move',
                'POST api/movies/<id>/restore' => 'api-movies/restore',
                'POST api/movies/duplicates/check' => 'api-movies/duplicates-check',
                'GET api/tags' => 'api-tags/index',
                'POST api/tags' => 'api-tags/create',
                'PATCH api/tags/<id>' => 'api-tags/update',
                'DELETE api/tags/<id>' => 'api-tags/delete',
                'GET api/tabs/counts' => 'api-tabs/counts',
                'GET api/users' => 'api-users/index',
                'POST api/users/<userId>/follow' => 'api-users/follow',
                'DELETE api/users/<userId>/follow' => 'api-users/unfollow',
                'GET api/users/<userId>/movies/filters' => 'api-users/movies-filters',
                'GET api/users/<userId>/movies/<movieId>' => 'api-users/movie',
                'GET api/users/<userId>/movies' => 'api-users/movies',
                'GET api/notifications' => 'api-notifications/index',
                'GET api/notifications/status' => 'api-notifications/status',
                'POST api/notifications/read' => 'api-notifications/read',
                'GET api/notifications/users' => 'api-notifications/users',
                'docs' => 'docs/index',
                'docs/json' => 'docs/json',
            ],
        ],
    ],
    'params' => $params,
];
