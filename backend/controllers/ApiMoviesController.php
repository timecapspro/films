<?php

namespace backend\controllers;

use common\models\Movie;
use common\models\User;
use Yii;
use yii\db\Expression;
use backend\components\JwtAuthFilter;
use yii\filters\ContentNegotiator;
use yii\filters\VerbFilter;
use yii\helpers\FileHelper;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;
use yii\helpers\Url;

class ApiMoviesController extends Controller
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
                    'index' => ['get'],
                    'view' => ['get'],
                    'create' => ['post'],
                    'update' => ['patch', 'post'],
                    'delete' => ['delete'],
                    'move' => ['post'],
                    'restore' => ['post'],
                    'duplicates-check' => ['post'],
                    'export' => ['get'],
                    'import-kinopoisk' => ['post'],
                    'copy' => ['post'],
                ],
            ],
        ]);
    }

    public function actionIndex()
    {
        $request = Yii::$app->request;
        $list = $request->get('list', Movie::LIST_MY);
        if (!in_array($list, [Movie::LIST_MY, Movie::LIST_LATER, Movie::LIST_DELETED], true)) {
            throw new BadRequestHttpException('Invalid list.');
        }
        $page = max((int)$request->get('page', 1), 1);
        $pageSize = (int)$request->get('pageSize', 12);
        $pageSize = $pageSize > 0 ? $pageSize : 12;
        $sort = $request->get('sort', 'added_desc');
        $q = $request->get('q');

        $query = Movie::find()
            ->where(['user_id' => Yii::$app->user->id, 'list' => $list]);

        if (!empty($q)) {
            $query->andWhere([
                'or',
                ['like', 'title', $q],
                ['like', 'description', $q],
                ['like', 'notes', $q],
            ]);
        }

        $this->applySort($query, $sort, $list);

        $total = (clone $query)->count();
        $items = $query
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->all();

        return [
            'items' => array_map([$this, 'serializeMovie'], $items),
            'total' => (int)$total,
        ];
    }

    public function actionView($id)
    {
        $movie = $this->findMovie($id);

        return ['movie' => $this->serializeMovie($movie)];
    }

    public function actionCreate()
    {
        $movie = new Movie();
        $movie->user_id = Yii::$app->user->id;

        $data = Yii::$app->request->bodyParams;
        $this->applyMovieData($movie, $data);

        if ($movie->list === Movie::LIST_DELETED) {
            Yii::$app->response->statusCode = 422;
            return ['errors' => ['list' => ['List cannot be deleted on create.']]];
        }

        $file = UploadedFile::getInstanceByName('poster');
        if ($file !== null) {
            $movie->poster_path = $this->savePoster($movie, $file);
        }

        if (!$movie->save()) {
            Yii::$app->response->statusCode = 422;
            return ['errors' => $movie->getErrors()];
        }

        return ['movie' => $this->serializeMovie($movie)];
    }

    public function actionUpdate($id)
    {
        $movie = $this->findMovie($id);

        $data = Yii::$app->request->bodyParams;
        $this->applyMovieData($movie, $data, true);

        $removePoster = isset($data['removePoster']) && $data['removePoster'] === '1';
        if ($removePoster) {
            $this->deletePosterFile($movie->poster_path);
            $movie->poster_path = null;
        }

        $file = UploadedFile::getInstanceByName('poster');
        if ($file !== null) {
            $this->deletePosterFile($movie->poster_path);
            $movie->poster_path = $this->savePoster($movie, $file);
        }

        if (!$movie->save()) {
            Yii::$app->response->statusCode = 422;
            return ['errors' => $movie->getErrors()];
        }

        return ['movie' => $this->serializeMovie($movie)];
    }

    public function actionDelete($id)
    {
        $movie = $this->findMovie($id);
        $hard = Yii::$app->request->get('hard');

        if ($hard === '1' || $hard === 1) {
            $this->deletePosterFile($movie->poster_path);
            $movie->delete();
        } else {
            if ($movie->list !== Movie::LIST_DELETED) {
                $movie->deleted_from_list = $movie->list;
                $movie->list = Movie::LIST_DELETED;
                $this->setDeletedAt($movie, gmdate('Y-m-d H:i:s'));
                $movie->save(false);
            }
        }

        return ['ok' => true];
    }

    public function actionMove($id)
    {
        $movie = $this->findMovie($id);
        $data = Yii::$app->request->bodyParams;
        $toList = $data['toList'] ?? null;
        if (!in_array($toList, [Movie::LIST_MY, Movie::LIST_LATER], true)) {
            throw new BadRequestHttpException('Invalid toList.');
        }

        $movie->list = $toList;
        $movie->deleted_from_list = null;
        $movie->save(false);

        return ['ok' => true];
    }

    public function actionRestore($id)
    {
        $movie = $this->findMovie($id);
        if ($movie->list !== Movie::LIST_DELETED) {
            return ['ok' => true];
        }

        $targetList = $movie->deleted_from_list ?: Movie::LIST_MY;
        $duplicates = $this->findDuplicates($movie->title, $movie->year, $movie->id, true);
        if (!empty($duplicates)) {
            Yii::$app->response->statusCode = 409;
            return ['duplicates' => $duplicates];
        }

        $movie->list = $targetList;
        $movie->deleted_from_list = null;
        $this->setDeletedAt($movie, null);
        $movie->save(false);

        return ['ok' => true];
    }

    public function actionDuplicatesCheck()
    {
        $data = Yii::$app->request->bodyParams;
        $title = isset($data['title']) ? trim($data['title']) : '';
        if ($title === '') {
            throw new BadRequestHttpException('Title is required.');
        }
        $year = $data['year'] ?? null;
        $excludeId = $data['excludeId'] ?? null;

        return ['duplicates' => $this->findDuplicates($title, $year, $excludeId, false)];
    }

    public function actionExport()
    {
        $request = Yii::$app->request;
        $scope = $request->get('scope', 'all');
        if (!in_array($scope, ['all', Movie::LIST_MY, Movie::LIST_LATER, Movie::LIST_DELETED], true)) {
            throw new BadRequestHttpException('Invalid scope.');
        }
        $q = $request->get('q');

        $query = Movie::find()->where(['user_id' => Yii::$app->user->id]);
        if ($scope !== 'all') {
            $query->andWhere(['list' => $scope]);
        }
        if (!empty($q)) {
            $query->andWhere([
                'or',
                ['like', 'title', $q],
                ['like', 'description', $q],
                ['like', 'notes', $q],
            ]);
        }
        $query->orderBy(['added_at' => SORT_DESC]);

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, [
            'id',
            'list',
            'title',
            'year',
            'runtimeMin',
            'genresCsv',
            'description',
            'notes',
            'watched',
            'rating',
            'watchedAt',
            'posterUrl',
            'url',
            'addedAt',
        ]);

        foreach ($query->each() as $movie) {
            fputcsv($handle, [
                $movie->id,
                $movie->list,
                $movie->title,
                $movie->year,
                $movie->runtime_min,
                $movie->genres_csv,
                $movie->description,
                $movie->notes,
                $movie->watched ? '1' : '0',
                $movie->rating,
                $movie->watched_at,
                $this->getPosterUrl($movie),
                $movie->url,
                $this->formatAddedAt($movie->added_at),
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $response = Yii::$app->response;
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="movies.csv"');

        return $csv;
    }

    public function actionImportKinopoisk()
    {
        $data = Yii::$app->request->bodyParams;
        $list = $data['list'] ?? null;
        if (!in_array($list, [Movie::LIST_MY, Movie::LIST_LATER], true)) {
            throw new BadRequestHttpException('Invalid list.');
        }

        $kinopoiskUrl = isset($data['kinopoiskUrl']) ? trim((string)$data['kinopoiskUrl']) : '';
        if ($kinopoiskUrl === '') {
            throw new BadRequestHttpException('kinopoiskUrl is required.');
        }

        $parsedUrl = $this->parseKinopoiskUrl($kinopoiskUrl);
        if ($parsedUrl === null) {
            throw new BadRequestHttpException('Invalid kinopoiskUrl.');
        }

        $watched = $this->normalizeBool($data['watched'] ?? false);
        $rating = $data['rating'] ?? null;
        $watchedAt = $data['watchedAt'] ?? null;
        if ($watched) {
            if ($rating === null || $watchedAt === null || $watchedAt === '') {
                throw new BadRequestHttpException('rating and watchedAt are required when watched.');
            }
        }

        $film = $this->fetchKinopoiskFilm($parsedUrl['id']);
        if ($film === null) {
            if (Yii::$app->response->statusCode === 500) {
                return ['message' => 'Kinopoisk API key is not configured.'];
            }
            throw new NotFoundHttpException('Movie not found.');
        }

        $title = $this->pickTitle($film);
        if ($title === '') {
            throw new NotFoundHttpException('Movie not found.');
        }

        $year = $this->normalizeInt($film['year'] ?? null);
        $duplicates = $this->findDuplicates($title, $year, null, true);
        if (!empty($duplicates)) {
            Yii::$app->response->statusCode = 409;
            return ['duplicates' => $duplicates];
        }

        $movie = new Movie();
        $movie->user_id = Yii::$app->user->id;
        $movie->list = $list;
        $movie->title = $title;
        $movie->year = $year;
        $movie->runtime_min = $this->normalizeInt($film['filmLength'] ?? null);
        $movie->genres_csv = $this->formatGenres($film['genres'] ?? []);
        $movie->description = $film['description'] ?? $film['shortDescription'] ?? null;
        $movie->notes = isset($data['notes']) ? trim((string)$data['notes']) : null;
        $movie->watched = $watched;
        if ($watched) {
            $movie->rating = $this->normalizeInt($rating);
            $movie->watched_at = (string)$watchedAt;
        } else {
            $movie->rating = null;
            $movie->watched_at = null;
        }
        $movie->url = $parsedUrl['url'];

        $posterUrl = $film['posterUrl'] ?? null;
        if (!empty($posterUrl)) {
            if (!$movie->id) {
                $movie->id = Yii::$app->security->generateRandomString(32);
            }
            $posterPath = $this->downloadPoster($movie->id, $posterUrl);
            if ($posterPath !== null) {
                $movie->poster_path = $posterPath;
            }
        }

        if (!$movie->save()) {
            Yii::$app->response->statusCode = 422;
            return ['errors' => $movie->getErrors()];
        }

        return ['movie' => $this->serializeMovie($movie)];
    }

    public function actionCopy()
    {
        $data = Yii::$app->request->bodyParams;
        $movieId = $data['movieId'] ?? null;
        $fromUserId = $data['fromUserId'] ?? null;

        if (!$movieId || !$fromUserId) {
            throw new BadRequestHttpException('movieId and fromUserId are required.');
        }

        $sourceUser = User::findOne(['id' => $fromUserId, 'status' => User::STATUS_ACTIVE, 'is_public' => 1]);
        if ($sourceUser === null) {
            throw new NotFoundHttpException('User not found.');
        }

        $sourceMovie = Movie::findOne(['id' => $movieId, 'user_id' => $fromUserId]);
        if ($sourceMovie === null || $sourceMovie->list === Movie::LIST_DELETED) {
            throw new NotFoundHttpException('Movie not found.');
        }

        if ($this->hasDuplicateForUser(Yii::$app->user->id, $sourceMovie->title, $sourceMovie->year)) {
            Yii::$app->response->statusCode = 409;
            return ['message' => 'Duplicate movie'];
        }

        $movie = new Movie();
        $movie->user_id = Yii::$app->user->id;
        $movie->list = Movie::LIST_MY;
        $movie->title = $sourceMovie->title;
        $movie->year = $sourceMovie->year;
        $movie->runtime_min = $sourceMovie->runtime_min;
        $movie->genres_csv = $sourceMovie->genres_csv;
        $movie->description = $sourceMovie->description;
        $movie->notes = null;
        $movie->watched = false;
        $movie->rating = null;
        $movie->watched_at = null;
        $movie->poster_path = $sourceMovie->poster_path;
        $movie->url = $sourceMovie->url;

        if (!$movie->save()) {
            Yii::$app->response->statusCode = 422;
            return ['errors' => $movie->getErrors()];
        }

        return ['ok' => true, 'movieId' => $movie->id];
    }

    private function findMovie($id)
    {
        $movie = Movie::findOne(['id' => $id, 'user_id' => Yii::$app->user->id]);
        if ($movie === null) {
            throw new NotFoundHttpException('Movie not found.');
        }

        return $movie;
    }

    private function applyMovieData(Movie $movie, array $data, bool $isUpdate = false): void
    {
        if (array_key_exists('list', $data)) {
            $movie->list = $data['list'];
        } elseif (!$isUpdate && $movie->list === null) {
            $movie->list = null;
        }

        if (isset($data['title'])) {
            $movie->title = trim((string)$data['title']);
        }

        $movie->year = $this->normalizeInt($data['year'] ?? $movie->year);
        $movie->runtime_min = $this->normalizeInt($data['runtimeMin'] ?? $movie->runtime_min);
        if (array_key_exists('genresCsv', $data)) {
            $movie->genres_csv = $data['genresCsv'] !== null ? (string)$data['genresCsv'] : null;
        }
        if (array_key_exists('description', $data)) {
            $movie->description = $data['description'] !== null ? (string)$data['description'] : null;
        }
        if (array_key_exists('notes', $data)) {
            $movie->notes = $data['notes'] !== null ? (string)$data['notes'] : null;
        }
        if (array_key_exists('url', $data)) {
            $movie->url = $data['url'] !== null ? (string)$data['url'] : null;
        }

        if (array_key_exists('watched', $data)) {
            $movie->watched = $data['watched'] === '1' || $data['watched'] === 1 || $data['watched'] === true;
        }

        if (array_key_exists('rating', $data)) {
            $movie->rating = $this->normalizeInt($data['rating']);
        }

        if (array_key_exists('watchedAt', $data)) {
            $movie->watched_at = $data['watchedAt'] !== null ? (string)$data['watchedAt'] : null;
        }

        if (!$movie->watched) {
            $movie->rating = null;
            $movie->watched_at = null;
        }
    }

    private function normalizeInt($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (int)$value;
    }

    private function normalizeBool($value): bool
    {
        return $value === '1' || $value === 1 || $value === true;
    }

    private function applySort($query, string $sort, string $list): void
    {
        if (str_starts_with($sort, 'deleted_at_') && $list !== Movie::LIST_DELETED) {
            $sort = 'added_desc';
        }

        switch ($sort) {
            case 'added_asc':
                $query->orderBy(['added_at' => SORT_ASC]);
                break;
            case 'title_asc':
                $query->orderBy(['title' => SORT_ASC]);
                break;
            case 'title_desc':
                $query->orderBy(['title' => SORT_DESC]);
                break;
            case 'rating_desc':
                $query->orderBy(new Expression('rating IS NULL, rating DESC, added_at DESC'));
                break;
            case 'rating_asc':
                $query->orderBy(new Expression('rating IS NOT NULL, rating ASC, added_at DESC'));
                break;
            case 'year_desc':
                $query->orderBy(['year' => SORT_DESC, 'added_at' => SORT_DESC]);
                break;
            case 'year_asc':
                $query->orderBy(['year' => SORT_ASC, 'added_at' => SORT_DESC]);
                break;
            case 'watched_at_desc':
                $query->orderBy(new Expression('watched_at IS NULL, watched_at DESC, added_at DESC'));
                break;
            case 'watched_at_asc':
                $query->orderBy(new Expression('watched_at IS NOT NULL, watched_at ASC, added_at DESC'));
                break;
            case 'deleted_at_desc':
                $query->orderBy(new Expression('deleted_at IS NULL, deleted_at DESC, added_at DESC'));
                break;
            case 'deleted_at_asc':
                $query->orderBy(new Expression('deleted_at IS NOT NULL, deleted_at ASC, added_at DESC'));
                break;
            case 'added_desc':
            default:
                $query->orderBy(['added_at' => SORT_DESC]);
                break;
        }
    }

    private function serializeMovie(Movie $movie): array
    {
        return [
            'id' => $movie->id,
            'list' => $movie->list,
            'title' => $movie->title,
            'year' => $movie->year === null ? null : (int)$movie->year,
            'runtimeMin' => $movie->runtime_min === null ? null : (int)$movie->runtime_min,
            'genresCsv' => $movie->genres_csv ?? '',
            'description' => $movie->description ?? '',
            'notes' => $movie->notes ?? '',
            'watched' => (bool)$movie->watched,
            'rating' => $movie->rating === null ? null : (int)$movie->rating,
            'watchedAt' => $movie->watched_at ?: null,
            'posterUrl' => $this->getPosterUrl($movie),
            'url' => $movie->url ?? '',
            'addedAt' => $this->formatAddedAt($movie->added_at),
        ];
    }

    private function setDeletedAt(Movie $movie, ?string $value): void
    {
        if ($movie->hasAttribute('deleted_at')) {
            $movie->setAttribute('deleted_at', $value);
        }
    }

    private function getPosterUrl(Movie $movie): ?string
    {
        if ($movie->poster_path === null || $movie->poster_path === '') {
            return null;
        }

        return Url::to('@web/' . $movie->poster_path, true);
    }

    private function formatAddedAt(string $addedAt): string
    {
        $timestamp = strtotime($addedAt);
        if ($timestamp === false) {
            return $addedAt;
        }

        return gmdate('c', $timestamp);
    }

    private function savePoster(Movie $movie, UploadedFile $file): string
    {
        if ($file->error !== UPLOAD_ERR_OK) {
            throw new BadRequestHttpException('Poster upload failed with error code: ' . $file->error);
        }
        $uploadDir = Yii::getAlias('@backend/web/uploads/posters');
        FileHelper::createDirectory($uploadDir, 0777, true);
        if (!is_writable($uploadDir)) {
            @chmod($uploadDir, 0777);
        }
        if (!is_writable($uploadDir)) {
            throw new \RuntimeException('Upload directory is not writable: ' . $uploadDir);
        }

        if (!$movie->id) {
            $movie->id = Yii::$app->security->generateRandomString(32);
        }

        $extension = $file->getExtension();
        $filename = $movie->id . ($extension ? '.' . $extension : '');
        $path = $uploadDir . DIRECTORY_SEPARATOR . $filename;
        $saved = $file->saveAs($path);
        if (!$saved || !is_file($path)) {
            $details = [
                'error' => $file->error,
                'tempName' => $file->tempName,
                'exists' => $file->tempName ? (int)is_file($file->tempName) : 0,
                'path' => $path,
            ];
            throw new \RuntimeException('Failed to save poster file: ' . json_encode($details));
        }

        return 'uploads/posters/' . $filename;
    }

    private function downloadPoster(string $movieId, string $posterUrl): ?string
    {
        $posterDir = Yii::getAlias('@backend/web/uploads/posters');
        FileHelper::createDirectory($posterDir, 0777, true);

        $extension = pathinfo(parse_url($posterUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION);
        $extension = $extension ?: 'jpg';
        $filename = $movieId . '.' . $extension;
        $fullPath = $posterDir . DIRECTORY_SEPARATOR . $filename;

        $data = @file_get_contents($posterUrl);
        if ($data === false) {
            return null;
        }

        $saved = file_put_contents($fullPath, $data);
        if ($saved === false) {
            return null;
        }

        return 'uploads/posters/' . $filename;
    }

    private function deletePosterFile(?string $posterPath): void
    {
        if (!$posterPath) {
            return;
        }
        $fullPath = Yii::getAlias('@frontend/web/' . $posterPath);
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    private function findDuplicates(string $title, $year, ?string $excludeId, bool $excludeDeleted): array
    {
        $query = Movie::find()
            ->where(['user_id' => Yii::$app->user->id])
            ->andWhere(['=', new Expression('LOWER(title)'), mb_strtolower($title)]);

        if ($year !== null && $year !== '') {
            $query->andWhere(['year' => (int)$year]);
        }

        if ($excludeId) {
            $query->andWhere(['<>', 'id', $excludeId]);
        }

        if ($excludeDeleted) {
            $query->andWhere(['<>', 'list', Movie::LIST_DELETED]);
        }

        return array_map(function (Movie $movie) {
            return [
                'id' => $movie->id,
                'title' => $movie->title,
                'year' => $movie->year === null ? null : (int)$movie->year,
                'list' => $movie->list,
            ];
        }, $query->all());
    }

    private function hasDuplicateForUser(int $userId, string $title, $year): bool
    {
        $query = Movie::find()
            ->where(['user_id' => $userId])
            ->andWhere(['<>', 'list', Movie::LIST_DELETED])
            ->andWhere(['=', new Expression('LOWER(title)'), mb_strtolower($title)]);

        if ($year !== null && $year !== '') {
            $query->andWhere(['year' => (int)$year]);
        }

        return $query->exists();
    }

    private function parseKinopoiskUrl(string $url): ?array
    {
        if (preg_match('~^https?://(?:www\\.)?kinopoisk\\.ru/(film|series)/(\\d+)/?~', $url, $matches)) {
            $type = $matches[1];
            $id = (int)$matches[2];
            return [
                'type' => $type,
                'id' => $id,
                'url' => "https://www.kinopoisk.ru/{$type}/{$id}/",
            ];
        }

        return null;
    }

    private function fetchKinopoiskFilm(int $kinopoiskId): ?array
    {
        $apiKey = $this->resolveKinopoiskApiKey();
        if ($apiKey === '') {
            Yii::$app->response->statusCode = 500;
            return null;
        }

        $response = $this->kinopoiskApiGet(
            $apiKey,
            'https://kinopoiskapiunofficial.tech',
            "/api/v2.2/films/{$kinopoiskId}"
        );

        return $response ?: null;
    }

    private function kinopoiskApiGet(string $apiKey, string $baseUrl, string $url, array $params = []): ?array
    {
        $requestUrl = rtrim($baseUrl, '/') . $url;
        if (!empty($params)) {
            $requestUrl .= '?' . http_build_query($params);
        }

        $ch = curl_init($requestUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'X-API-KEY: ' . $apiKey,
            ],
            CURLOPT_TIMEOUT => 20,
        ]);
        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $status < 200 || $status >= 300) {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return null;
        }

        return $data;
    }

    private function pickTitle(array $film): string
    {
        foreach (['nameRu', 'nameOriginal', 'nameEn'] as $field) {
            if (!empty($film[$field])) {
                return trim((string)$film[$field]);
            }
        }
        return '';
    }

    private function formatGenres(array $genres): string
    {
        if (empty($genres)) {
            return '';
        }
        $names = array_map(function ($genre) {
            return $genre['genre'] ?? '';
        }, $genres);
        $names = array_filter($names, static fn($name) => $name !== '');
        return implode(', ', $names);
    }

    private function resolveKinopoiskApiKey(): string
    {
        $apiKey = (string)(Yii::$app->params['kinopoiskApiKey'] ?? '');
        if ($apiKey !== '') {
            return $apiKey;
        }

        $apiKey = (string)(getenv('KINOPOISK_API_KEY') ?: '');
        if ($apiKey !== '') {
            return $apiKey;
        }

        $consoleParams = $this->loadParamsFile('@console/config/params-local.php');
        $apiKey = (string)($consoleParams['kinopoiskApiKey'] ?? '');
        if ($apiKey !== '') {
            return $apiKey;
        }

        $commonParams = $this->loadParamsFile('@common/config/params-local.php');
        return (string)($commonParams['kinopoiskApiKey'] ?? '');
    }

    private function loadParamsFile(string $alias): array
    {
        $path = Yii::getAlias($alias);
        if (!is_file($path)) {
            return [];
        }

        $params = require $path;
        return is_array($params) ? $params : [];
    }
}
