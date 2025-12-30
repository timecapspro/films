<?php

namespace backend\controllers;

use backend\components\JwtAuthFilter;
use common\models\User;
use Yii;
use yii\filters\ContentNegotiator;
use yii\filters\VerbFilter;
use yii\helpers\FileHelper;
use yii\helpers\Url;
use yii\validators\EmailValidator;
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
                    'security' => ['patch'],
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
        $request = Yii::$app->request;
        $data = $request->post();
        $uploadedFiles = [];

        if (empty($data) && $request->getIsPatch()) {
            $data = $request->getBodyParams();
        }

        if ($request->getIsPatch()) {
            $contentType = (string)$request->getHeaders()->get('Content-Type', '');
            if (stripos($contentType, 'application/x-www-form-urlencoded') !== false && empty($data)) {
                $rawBody = $request->getRawBody();
                if ($rawBody !== '') {
                    parse_str($rawBody, $data);
                }
            } elseif (stripos($contentType, 'multipart/form-data') !== false) {
                $rawBody = $request->getRawBody();
                if ($rawBody !== '') {
                    $parsed = $this->parseMultipartFormData($rawBody, $contentType);
                    $data = array_merge($data, $parsed['fields']);
                    $uploadedFiles = $parsed['files'];
                }
            }
        }
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
        if ($file === null && isset($uploadedFiles['avatar'])) {
            $file = $this->buildUploadedFile($uploadedFiles['avatar']);
        }
        if (empty($data) && $file === null) {
            throw new BadRequestHttpException(
                'Empty profile update payload. Use JSON or x-www-form-urlencoded for PATCH, or POST for multipart data.'
            );
        }
        if ($file !== null) {
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
            $extension = strtolower($file->getExtension());
            if ($extension === '') {
                $extension = $this->mapAvatarExtension($file->type);
            }
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

    public function actionSecurity()
    {
        /** @var User $user */
        $user = Yii::$app->user->identity;
        $data = Yii::$app->request->bodyParams;

        $username = array_key_exists('username', $data) ? trim((string)$data['username']) : null;
        $email = array_key_exists('email', $data) ? trim((string)$data['email']) : null;
        $currentPassword = isset($data['currentPassword']) ? (string)$data['currentPassword'] : '';

        if ($username === null && $email === null) {
            Yii::$app->response->statusCode = 400;
            return ['message' => 'username or email is required.'];
        }

        if ($currentPassword !== '' && !$user->validatePassword($currentPassword)) {
            Yii::$app->response->statusCode = 403;
            return ['message' => 'Invalid current password.'];
        }

        if ($username !== null) {
            if (mb_strlen($username) < 3) {
                Yii::$app->response->statusCode = 400;
                return ['message' => 'Username is too short.'];
            }
            if (User::find()->where(['username' => $username])->andWhere(['<>', 'id', $user->id])->exists()) {
                Yii::$app->response->statusCode = 409;
                return ['message' => 'Логин уже занят'];
            }
            $user->username = $username;
        }

        if ($email !== null) {
            if (!(new EmailValidator())->validate($email)) {
                Yii::$app->response->statusCode = 400;
                return ['message' => 'Некорректные данные'];
            }
            if (User::find()->where(['email' => $email])->andWhere(['<>', 'id', $user->id])->exists()) {
                Yii::$app->response->statusCode = 409;
                return ['message' => 'Email уже зарегистрирован'];
            }
            $user->email = $email;
        }

        if (!$user->save(false)) {
            Yii::$app->response->statusCode = 400;
            return ['message' => 'Failed to update security profile.'];
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

    private function parseMultipartFormData(string $body, string $contentType): array
    {
        $boundary = '';
        if (preg_match('/boundary=(?:"([^"]+)"|([^;]+))/i', $contentType, $matches)) {
            $boundary = $matches[1] !== '' ? $matches[1] : $matches[2];
        }
        $boundary = trim($boundary);
        if ($boundary === '') {
            return ['fields' => [], 'files' => []];
        }

        $fields = [];
        $files = [];
        $parts = explode('--' . $boundary, $body);
        foreach ($parts as $part) {
            $part = ltrim($part, "\r\n");
            if ($part === '' || $part === '--' || $part === "--\r\n") {
                continue;
            }

            $sections = preg_split("/\r\n\r\n|\n\n/", $part, 2);
            if (!$sections || count($sections) < 2) {
                continue;
            }
            [$rawHeaders, $content] = $sections;
            $content = ltrim($content, "\r\n");
            $content = rtrim($content, "\r\n");

            $headers = [];
            foreach (preg_split("/\r\n|\n/", $rawHeaders) as $headerLine) {
                if (strpos($headerLine, ':') === false) {
                    continue;
                }
                [$name, $value] = explode(':', $headerLine, 2);
                $headers[strtolower(trim($name))] = trim($value);
            }

            if (!isset($headers['content-disposition'])) {
                continue;
            }

            $disposition = $headers['content-disposition'];
            if (!preg_match('/name="([^"]+)"/i', $disposition, $nameMatch)) {
                continue;
            }
            $fieldName = $nameMatch[1];

            if (preg_match('/filename="([^"]*)"/i', $disposition, $fileMatch)) {
                $filename = $fileMatch[1];
                if ($filename === '') {
                    continue;
                }
                $tempName = tempnam(sys_get_temp_dir(), 'patch_upload_');
                if ($tempName === false) {
                    continue;
                }
                file_put_contents($tempName, $content);
                $files[$fieldName] = [
                    'name' => $filename,
                    'type' => $headers['content-type'] ?? 'application/octet-stream',
                    'tempName' => $tempName,
                    'size' => strlen($content),
                    'error' => UPLOAD_ERR_OK,
                ];
            } else {
                $fields[$fieldName] = $content;
            }
        }

        return ['fields' => $fields, 'files' => $files];
    }

    private function buildUploadedFile(array $fileData): UploadedFile
    {
        return new UploadedFile([
            'name' => $fileData['name'],
            'tempName' => $fileData['tempName'],
            'type' => $fileData['type'],
            'size' => $fileData['size'],
            'error' => $fileData['error'],
        ]);
    }

    private function mapAvatarExtension(?string $mimeType): string
    {
        $mimeType = strtolower((string)$mimeType);
        $map = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        return $map[$mimeType] ?? '';
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

        $saved = $this->saveUploadedFile($file, $path);
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

    private function saveUploadedFile(UploadedFile $file, string $path): bool
    {
        if (is_uploaded_file($file->tempName)) {
            return $file->saveAs($path);
        }

        $saved = $file->saveAs($path, false);
        if (is_file($file->tempName)) {
            @unlink($file->tempName);
        }

        return $saved;
    }
}
