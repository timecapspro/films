<?php

namespace frontend\controllers;

use common\models\User;
use Yii;
use yii\filters\ContentNegotiator;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;
use yii\web\UnauthorizedHttpException;

class ApiAuthController extends Controller
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
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'login' => ['post'],
                    'logout' => ['post'],
                    'me' => ['get'],
                ],
            ],
        ]);
    }

    public function actionLogin()
    {
        $body = Yii::$app->request->bodyParams;
        $email = isset($body['email']) ? trim($body['email']) : null;
        $password = $body['password'] ?? null;

        if ($email === '' || $email === null || $password === null) {
            Yii::$app->response->statusCode = 422;
            return ['message' => 'Email and password are required.'];
        }

        $user = User::findOne(['email' => $email, 'status' => User::STATUS_ACTIVE]);
        if (!$user || !$user->validatePassword($password)) {
            throw new UnauthorizedHttpException('Invalid credentials.');
        }

        Yii::$app->user->login($user, 3600 * 24 * 30);

        return ['email' => $user->email];
    }

    public function actionLogout()
    {
        Yii::$app->user->logout();

        return ['ok' => true];
    }

    public function actionMe()
    {
        if (Yii::$app->user->isGuest) {
            throw new UnauthorizedHttpException('Not authenticated.');
        }

        /** @var User $user */
        $user = Yii::$app->user->identity;

        return ['email' => $user->email];
    }
}
