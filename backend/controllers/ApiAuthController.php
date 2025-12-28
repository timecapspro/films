<?php

namespace backend\controllers;

use backend\components\JwtAuthFilter;
use backend\components\JwtService;
use common\models\User;
use Yii;
use yii\filters\ContentNegotiator;
use yii\filters\VerbFilter;
use yii\validators\EmailValidator;
use yii\web\Controller;
use yii\web\Response;

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
            'authenticator' => [
                'class' => JwtAuthFilter::class,
                'except' => ['login', 'register'],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'login' => ['post'],
                    'register' => ['post'],
                    'me' => ['get'],
                ],
            ],
        ]);
    }

    public function actionRegister()
    {
        $body = Yii::$app->request->bodyParams;
        $username = isset($body['username']) ? trim((string)$body['username']) : '';
        $email = isset($body['email']) ? trim((string)$body['email']) : '';
        $password = isset($body['password']) ? (string)$body['password'] : '';

        if (
            $username === ''
            || $email === ''
            || $password === ''
            || mb_strlen($username) < 3
            || mb_strlen($password) < 6
            || !(new EmailValidator())->validate($email)
        ) {
            Yii::$app->response->statusCode = 400;
            return ['message' => 'Некорректные данные'];
        }

        if (User::find()->where(['username' => $username])->exists()) {
            Yii::$app->response->statusCode = 409;
            return ['message' => 'Логин уже занят'];
        }

        if (User::find()->where(['email' => $email])->exists()) {
            Yii::$app->response->statusCode = 409;
            return ['message' => 'Email уже зарегистрирован'];
        }

        $user = new User();
        $user->username = $username;
        $user->email = $email;
        $user->status = User::STATUS_ACTIVE;
        $user->setPassword($password);
        $user->generateAuthKey();

        if (!$user->save(false)) {
            Yii::$app->response->statusCode = 400;
            return ['message' => 'Некорректные данные'];
        }

        Yii::$app->response->statusCode = 201;
        return [
            'message' => 'Регистрация успешна',
            'email' => $user->email,
        ];
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
            Yii::$app->response->statusCode = 401;
            return ['message' => 'Invalid credentials'];
        }

        $secret = JwtService::resolveSecret();
        if (empty($secret)) {
            Yii::$app->response->statusCode = 500;
            return ['message' => 'JWT secret is not configured.'];
        }

        $jwt = new JwtService($secret, Yii::$app->params['jwtTtl'] ?? null);

        return [
            'token' => $jwt->issueToken($user),
            'email' => $user->email,
        ];
    }

    public function actionMe()
    {
        if (Yii::$app->user->isGuest) {
            Yii::$app->response->statusCode = 401;
            return ['message' => 'Not authenticated'];
        }

        /** @var User $user */
        $user = Yii::$app->user->identity;

        return ['email' => $user->email];
    }
}
