<?php

namespace backend\controllers;

use backend\components\JwtAuthFilter;
use common\models\User;
use Yii;
use yii\filters\ContentNegotiator;
use yii\filters\VerbFilter;
use yii\helpers\FileHelper;
use yii\helpers\Url;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\web\Response;
use yii\web\UploadedFile;

class ApiProfileController extends Controller
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
                    'view' => ['get'],
                    'update' => ['patch', 'post'],
                ],
            ],
        ]);
    }

    public function actionView()
    {
        /** @var User $user */
        $user = Yii::$app->user->identity;

        return $this->serializeProfile($user);
    }

    public function actionUpdate()
    {
        /** @var User $user */
        $user = Yii::$app->user->identity;
        $data = Yii::$app->request->post();
        $errors = [];

        if (array_key_exists('name', $data)) {
            $user->name = trim((string)$data['name']);
        }

        if (array_key_exists('about', $data)) {
            $about = trim((string)$data['about']);
            if (mb_strlen($about) > 400) {
                $errors['about'][] = 'About must be at most 400 characters.';
            } else {
                $user->about = $about;
            }
        }

        if (array_key_exists('gender', $data)) {
            $gender = (string)$data['gender'];
            if (!in_array($gender, ['m', 'f'], true)) {
                $errors['gender'][] = 'Invalid gender.';
            } else {
                $user->gender = $gender;
            }
        }

        if (array_key_exists('birthDate', $data)) {
            $birthDate = (string)$data['birthDate'];
            if (!$this->isValidDate($birthDate)) {
                $errors['birthDate'][] = 'Invalid birthDate format.';
            } else {
                $user->birth_date = $birthDate;
            }
        }

        if (array_key_exists('isPublic', $data)) {
            $user->is_public = $this->normalizeBool($data['isPublic']) ? 1 : 0;
        }

        $file = UploadedFile::getInstanceByName('avatar');
        if ($file !== null) {
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
            $extension = strtolower($file->getExtension());
            if (!in_array($extension, $allowedExtensions, true)) {
                throw new BadRequestHttpException('Invalid avatar format.');
            }
            $this->deleteAvatarFile($user->avatar_path ?? null);
            $user->avatar_path = $this->saveAvatar($user, $file);
        }

        if (!empty($errors)) {
            Yii::$app->response->statusCode = 400;
            return ['errors' => $errors];
        }

        if (!$user->save()) {
            Yii::$app->response->statusCode = 400;
            return ['errors' => $user->getErrors()];
        }

        return ['ok' => true];
    }

    private function serializeProfile(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'name' => $user->name ?? '',
            'avatar_url' => $this->getAvatarUrl($user),
            'about' => $user->about ?? '',
            'gender' => $user->gender ?? '',
            'birth_date' => $user->birth_date ?? '',
            'is_public' => (bool)$user->is_public,
            'created_at' => $this->formatDate($user->created_at),
        ];
    }

    private function getAvatarUrl(User $user): ?string
    {
        if (empty($user->avatar_path)) {
            return null;
        }

        return Url::to('@web/' . $user->avatar_path, true);
    }

    private function formatDate($timestamp): string
    {
        if ($timestamp === null || $timestamp === '') {
            return '';
        }
        $value = is_numeric($timestamp) ? (int)$timestamp : strtotime((string)$timestamp);
        if ($value === false) {
            return '';
        }
        return gmdate('Y-m-d', $value);
    }

    private function isValidDate(string $value): bool
    {
        $date = \DateTime::createFromFormat('Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function normalizeBool($value): bool
    {
        return $value === '1' || $value === 1 || $value === true;
    }

    private function saveAvatar(User $user, UploadedFile $file): string
    {
        if ($file->error !== UPLOAD_ERR_OK) {
            throw new BadRequestHttpException('Avatar upload failed with error code: ' . $file->error);
        }

        $uploadDir = Yii::getAlias('@backend/web/uploads/avatars');
        FileHelper::createDirectory($uploadDir, 0777, true);
        if (!is_writable($uploadDir)) {
            @chmod($uploadDir, 0777);
        }
        if (!is_writable($uploadDir)) {
            throw new \RuntimeException('Upload directory is not writable: ' . $uploadDir);
        }

        $extension = $file->getExtension();
        if ($extension === '') {
            $extension = pathinfo($file->name, PATHINFO_EXTENSION);
        }
        $extension = $extension ?: 'jpg';
        $filename = 'user-' . $user->id . '-' . Yii::$app->security->generateRandomString(12) . '.' . $extension;
        $path = $uploadDir . DIRECTORY_SEPARATOR . $filename;

        $saved = $file->saveAs($path);
        if (!$saved || !is_file($path)) {
            throw new \RuntimeException('Failed to save avatar file.');
        }

        return 'uploads/avatars/' . $filename;
    }

    private function deleteAvatarFile(?string $avatarPath): void
    {
        if (!$avatarPath) {
            return;
        }

        $fullPath = Yii::getAlias('@backend/web/' . $avatarPath);
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }
}
