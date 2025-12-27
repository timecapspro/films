<?php

namespace backend\controllers;

use Yii;
use yii\helpers\Url;
use yii\web\Controller;
use yii\web\Response;

class DocsController extends Controller
{
    public $layout = false;

    public function actionIndex(): string
    {
        return $this->render('index', [
            'specUrl' => Url::to(['docs/json']),
        ]);
    }

    public function actionJson(): array
    {
        $spec = require Yii::getAlias('@backend/config/openapi.php');
        $spec['servers'] = [
            [
                'url' => '/',
                'description' => 'Текущий домен',
            ],
        ];

        Yii::$app->response->format = Response::FORMAT_JSON;

        return $spec;
    }
}
