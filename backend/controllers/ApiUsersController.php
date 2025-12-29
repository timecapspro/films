<?php

namespace backend\controllers;

use backend\components\JwtAuthFilter;
use common\models\Movie;
use common\models\User;
use Yii;
use yii\db\Expression;
use yii\filters\ContentNegotiator;
use yii\filters\VerbFilter;
use yii\helpers\Url;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class ApiUsersController extends Controller
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
                    'movies' => ['get'],
                    'movie' => ['get'],
                ],
            ],
        ]);
    }

    public function actionIndex()
    {
        $q = Yii::$app->request->get('q');

        $query = User::find()
            ->alias('u')
            ->where(['u.status' => User::STATUS_ACTIVE, 'u.is_public' => 1]);

        if (!empty($q)) {
            $query->andWhere([
                'or',
                ['like', 'username', $q],
                ['like', 'name', $q],
            ]);
        }

        $query->addSelect([
            'u.*',
            'movies_count' => new Expression(
                '(SELECT COUNT(*) FROM {{%movie}} m WHERE m.user_id = u.id AND m.list <> :deleted)',
                [':deleted' => Movie::LIST_DELETED]
            ),
        ]);

        $users = $query->orderBy(['username' => SORT_ASC])->all();

        return [
            'users' => array_map([$this, 'serializeUser'], $users),
        ];
    }

    public function actionMovies($userId)
    {
        $user = $this->findPublicUser($userId);
        $request = Yii::$app->request;
        $page = max((int)$request->get('page', 1), 1);
        $pageSize = (int)$request->get('pageSize', 12);
        $pageSize = $pageSize > 0 ? $pageSize : 12;
        $sort = $request->get('sort', 'added_desc');
        $q = $request->get('q');

        $query = Movie::find()
            ->where(['user_id' => $user->id])
            ->andWhere(['<>', 'list', Movie::LIST_DELETED]);

        if (!empty($q)) {
            $query->andWhere([
                'or',
                ['like', 'title', $q],
                ['like', 'description', $q],
                ['like', 'notes', $q],
            ]);
        }

        $this->applySort($query, $sort);

        $total = (clone $query)->count();
        $items = $query
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->all();

        return [
            'items' => array_map([$this, 'serializePublicMovie'], $items),
            'total' => (int)$total,
        ];
    }

    public function actionMovie($userId, $movieId)
    {
        $user = $this->findPublicUser($userId);
        $movie = Movie::findOne(['id' => $movieId, 'user_id' => $user->id]);
        if ($movie === null || $movie->list === Movie::LIST_DELETED) {
            throw new NotFoundHttpException('Movie not found.');
        }

        return ['movie' => $this->serializeMovie($movie)];
    }

    private function findPublicUser($userId): User
    {
        $user = User::findOne(['id' => $userId, 'status' => User::STATUS_ACTIVE, 'is_public' => 1]);
        if ($user === null) {
            throw new NotFoundHttpException('User not found.');
        }

        return $user;
    }

    private function applySort($query, string $sort): void
    {
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
            case 'added_desc':
            default:
                $query->orderBy(['added_at' => SORT_DESC]);
                break;
        }
    }

    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name ?? '',
            'avatar_url' => $this->getAvatarUrl($user),
            'movies_count' => isset($user->movies_count) ? (int)$user->movies_count : 0,
        ];
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

    private function serializePublicMovie(Movie $movie): array
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
            'deletedAt' => null,
        ];
    }

    private function getAvatarUrl(User $user): ?string
    {
        if (empty($user->avatar_path)) {
            return null;
        }

        return Url::to('@web/' . $user->avatar_path, true);
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
}
