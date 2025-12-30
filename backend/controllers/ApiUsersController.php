<?php

namespace backend\controllers;

use backend\components\JwtAuthFilter;
use common\models\Movie;
use common\models\Tag;
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
                    'movies-filters' => ['get'],
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
            $normalized = ltrim(trim((string)$q), '@');
            $normalized = mb_strtolower($normalized);
            $query->andWhere([
                'or',
                ['like', new Expression('LOWER(username)'), $normalized],
                ['like', new Expression('LOWER(name)'), $normalized],
            ]);
        }

        $users = $query->orderBy(['username' => SORT_ASC])->all();
        $this->hydrateMoviesCount($users);

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
        $pageSize = min($pageSize, 100);
        $sort = $request->get('sort', 'added_desc');
        $q = $request->get('q');
        $yearFrom = $this->normalizeInt($request->get('yearFrom'));
        $yearTo = $this->normalizeInt($request->get('yearTo'));
        $genres = $this->parseCsvParam($request->get('genres'));
        $tagIds = $this->parseCsvParam($request->get('tags'));

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

        if ($yearFrom !== null) {
            $query->andWhere(['>=', 'year', $yearFrom]);
        }

        if ($yearTo !== null) {
            $query->andWhere(['<=', 'year', $yearTo]);
        }

        if (!empty($genres)) {
            $genreConditions = ['or'];
            foreach ($genres as $genre) {
                $genreConditions[] = ['like', 'genres_csv', $genre];
            }
            $query->andWhere($genreConditions);
        }

        if (!empty($tagIds)) {
            $query->joinWith('movieTags mt', false)
                ->andWhere(['mt.tag_id' => $tagIds])
                ->distinct();
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

    public function actionMoviesFilters($userId)
    {
        $user = $this->findPublicUser($userId);
        $moviesQuery = Movie::find()
            ->where(['user_id' => $user->id])
            ->andWhere(['<>', 'list', Movie::LIST_DELETED]);

        $years = (clone $moviesQuery)
            ->select(['year'])
            ->andWhere(['not', ['year' => null]])
            ->column();

        $yearMin = null;
        $yearMax = null;
        if (!empty($years)) {
            $yearMin = min($years);
            $yearMax = max($years);
        }

        $genres = [];
        foreach ((clone $moviesQuery)->select(['genres_csv'])->column() as $csv) {
            if (!$csv) {
                continue;
            }
            foreach (explode(',', $csv) as $genre) {
                $genre = trim($genre);
                if ($genre !== '') {
                    $genres[$genre] = true;
                }
            }
        }

        $tagQuery = Tag::find()
            ->alias('t')
            ->select(['t.id', 't.name', 't.color'])
            ->innerJoin('movie_tag mt', 'mt.tag_id = t.id')
            ->innerJoin('movie m', 'm.id = mt.movie_id')
            ->where(['t.user_id' => $user->id, 'm.user_id' => $user->id])
            ->andWhere(['<>', 'm.list', Movie::LIST_DELETED])
            ->distinct()
            ->orderBy(['t.name' => SORT_ASC])
            ->asArray()
            ->all();

        $genreList = array_values(array_keys($genres));
        sort($genreList, SORT_NATURAL | SORT_FLAG_CASE);

        return [
            'genres' => $genreList,
            'tags' => array_map([$this, 'serializeTagRow'], $tagQuery),
            'yearMin' => $yearMin === null ? null : (int)$yearMin,
            'yearMax' => $yearMax === null ? null : (int)$yearMax,
        ];
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

    private function hydrateMoviesCount(array $users): void
    {
        if (empty($users)) {
            return;
        }

        $userIds = array_map(static fn(User $user) => $user->id, $users);
        $counts = Movie::find()
            ->select(['user_id', 'movies_count' => new Expression('COUNT(*)')])
            ->where(['user_id' => $userIds])
            ->andWhere(['<>', 'list', Movie::LIST_DELETED])
            ->groupBy('user_id')
            ->asArray()
            ->all();

        $countsByUser = array_column($counts, 'movies_count', 'user_id');

        foreach ($users as $user) {
            $user->movies_count = isset($countsByUser[$user->id]) ? (int)$countsByUser[$user->id] : 0;
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

    private function normalizeInt($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (int)$value;
    }

    private function parseCsvParam($value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (is_array($value)) {
            $items = $value;
        } else {
            $items = explode(',', (string)$value);
        }
        $result = [];
        foreach ($items as $item) {
            $item = trim((string)$item);
            if ($item !== '') {
                $result[$item] = true;
            }
        }
        return array_keys($result);
    }

    private function serializeTagRow(array $row): array
    {
        return [
            'id' => $row['id'],
            'name' => $row['name'],
            'color' => $row['color'],
        ];
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
