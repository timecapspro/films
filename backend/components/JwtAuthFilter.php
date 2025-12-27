<?php

namespace backend\components;

use common\models\User;
use Yii;
use yii\base\ActionFilter;
use yii\web\Response;

class JwtAuthFilter extends ActionFilter
{
    public $except = [];

    public function beforeAction($action): bool
    {
        if (in_array($action->id, $this->except, true)) {
            return parent::beforeAction($action);
        }

        $authHeader = Yii::$app->request->getHeaders()->get('Authorization');
        if (!$authHeader || !preg_match('/^Bearer\s+(\S+)$/', $authHeader, $matches)) {
            $this->deny();
            return false;
        }

        $token = $matches[1];
        $jwt = new JwtService(Yii::$app->params['jwtSecret'] ?? null, Yii::$app->params['jwtTtl'] ?? null);
        $payload = $jwt->validateToken($token);
        if (!$payload || empty($payload['sub'])) {
            $this->deny();
            return false;
        }

        $user = User::findOne(['id' => $payload['sub'], 'status' => User::STATUS_ACTIVE]);
        if ($user === null) {
            $this->deny();
            return false;
        }

        Yii::$app->user->setIdentity($user);

        return parent::beforeAction($action);
    }

    private function deny(): void
    {
        Yii::$app->response->statusCode = 401;
        Yii::$app->response->format = Response::FORMAT_JSON;
        Yii::$app->response->data = ['message' => 'Not authenticated'];
    }
}
