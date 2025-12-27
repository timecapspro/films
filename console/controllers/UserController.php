<?php

namespace console\controllers;

use common\models\User;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

class UserController extends Controller
{
    public ?int $status = null;
    public ?string $email = null;
    public ?string $password = null;
    public ?string $username = null;

    public function options($actionID)
    {
        return array_merge(parent::options($actionID), ['status', 'email', 'password', 'username']);
    }

    public function optionAliases()
    {
        return array_merge(parent::optionAliases(), [
            'u' => 'username',
            'e' => 'email',
            'p' => 'password',
            's' => 'status',
        ]);
    }

    public function actionCreate($username, $email, $password)
    {
        $user = new User();
        $user->username = $username;
        $user->email = $email;
        $user->status = $this->status ?? User::STATUS_ACTIVE;
        $user->setPassword($password);
        $user->generateAuthKey();

        if (!$user->save()) {
            $this->stderr("Не удалось создать пользователя.\n", Console::FG_RED);
            $this->printErrors($user);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Пользователь создан с ID {$user->id}.\n", Console::FG_GREEN);
        return ExitCode::OK;
    }

    public function actionUpdate($id)
    {
        $user = User::findOne($id);
        if ($user === null) {
            $this->stderr("Пользователь с ID {$id} не найден.\n", Console::FG_RED);
            return ExitCode::NOINPUT;
        }

        if ($this->username !== null) {
            $user->username = $this->username;
        }

        if ($this->email !== null) {
            $user->email = $this->email;
        }

        if ($this->status !== null) {
            $user->status = $this->status;
        }

        if ($this->password !== null) {
            $user->setPassword($this->password);
            $user->generateAuthKey();
        }

        if (!$user->save()) {
            $this->stderr("Не удалось обновить пользователя.\n", Console::FG_RED);
            $this->printErrors($user);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Пользователь с ID {$user->id} обновлен.\n", Console::FG_GREEN);
        return ExitCode::OK;
    }

    public function actionDelete($id)
    {
        $user = User::findOne($id);
        if ($user === null) {
            $this->stderr("Пользователь с ID {$id} не найден.\n", Console::FG_RED);
            return ExitCode::NOINPUT;
        }

        if ($user->delete() === false) {
            $this->stderr("Не удалось удалить пользователя.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Пользователь с ID {$id} удален.\n", Console::FG_GREEN);
        return ExitCode::OK;
    }

    public function actionList()
    {
        $users = User::find()->orderBy(['id' => SORT_ASC])->all();
        if (!$users) {
            $this->stdout("Пользователи не найдены.\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        foreach ($users as $user) {
            $this->stdout(
                sprintf(
                    "ID: %d | %s | %s | статус: %d\n",
                    $user->id,
                    $user->username,
                    $user->email,
                    $user->status
                )
            );
        }

        return ExitCode::OK;
    }

    private function printErrors(User $user): void
    {
        foreach ($user->getFirstErrors() as $attribute => $error) {
            $this->stderr("- {$attribute}: {$error}\n", Console::FG_RED);
        }
    }
}
