<?php
$params = array_merge(
    require __DIR__ . '/../../common/config/params.php',
    require __DIR__ . '/../../common/config/params-local.php',
    require __DIR__ . '/params.php',
    require __DIR__ . '/params-local.php'
);

return [
    'id' => 'app-frontend',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'controllerNamespace' => 'frontend\controllers',
    'components' => [
        'request' => [
            'csrfParam' => '_csrf-frontend',
            'parsers' => [
                'application/json' => \yii\web\JsonParser::class,
            ],
        ],
        'user' => [
            'identityClass' => 'common\models\User',
            'enableAutoLogin' => true,
            'identityCookie' => ['name' => '_identity-frontend', 'httpOnly' => true],
        ],
        'session' => [
            // this is the name of the session cookie used for login on the frontend
            'name' => 'advanced-frontend',
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
            'errorAction' => 'site/error',
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
                'POST api/auth/login' => 'api-auth/login',
                'POST api/auth/logout' => 'api-auth/logout',
                'GET api/me' => 'api-auth/me',
                'GET api/movies' => 'api-movies/index',
                'GET api/movies/export.csv' => 'api-movies/export',
                'GET api/movies/<id>' => 'api-movies/view',
                'POST api/movies' => 'api-movies/create',
                'PATCH api/movies/<id>' => 'api-movies/update',
                'POST api/movies/<id>' => 'api-movies/update',
                'DELETE api/movies/<id>' => 'api-movies/delete',
                'POST api/movies/<id>/move' => 'api-movies/move',
                'POST api/movies/<id>/restore' => 'api-movies/restore',
                'POST api/movies/duplicates/check' => 'api-movies/duplicates-check',
            ],
        ],
    ],
    'params' => $params,
];
