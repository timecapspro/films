<?php

namespace backend\controllers;

use backend\components\JwtAuthFilter;
use common\models\Movie;
use common\models\User;
use Yii;
use yii\filters\ContentNegotiator;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;

class ApiTabsController extends Controller
{
    public $enableCsrfValidation = false;

    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'contentNegotiator' => [
                'class' => ContentNegotiator::class,
                'formats' => [
                    'application/json' => Response::FORMAT_JSON,
                ],
            ],
            'authenticator' => [
                'class' => JwtAuthFilter::class,
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'counts' => ['get'],
                ],
            ],
        ]);
    }

    public function actionCounts()
    {
        $userId = Yii::$app->user->id;

        $my = Movie::find()
            ->where(['user_id' => $userId, 'list' => Movie::LIST_MY])
            ->count();

        $later = Movie::find()
            ->where(['user_id' => $userId, 'list' => Movie::LIST_LATER])
            ->count();

        $deleted = Movie::find()
            ->where(['user_id' => $userId, 'list' => Movie::LIST_DELETED])
            ->count();

        $users = User::find()
            ->where(['status' => User::STATUS_ACTIVE, 'is_public' => 1])
            ->count();

        return [
            'my' => (int)$my,
            'later' => (int)$later,
            'deleted' => (int)$deleted,
            'users' => (int)$users,
        ];
    }
}
