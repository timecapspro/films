<?php

namespace backend\controllers;

use backend\components\JwtAuthFilter;
use common\models\Movie;
use common\models\Notification;
use common\models\User;
use common\models\UserFollow;
use Yii;
use yii\filters\ContentNegotiator;
use yii\filters\VerbFilter;
use yii\helpers\Url;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\web\Response;

class ApiNotificationsController extends Controller
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
                    'status' => ['get'],
                    'read' => ['post'],
                    'users' => ['get'],
                ],
            ],
        ]);
    }

    public function actionIndex()
    {
        $request = Yii::$app->request;
        $page = max((int)$request->get('page', 1), 1);
        $pageSize = (int)$request->get('pageSize', 20);
        $pageSize = max(10, min($pageSize, 100));

        $followedIds = $this->getFollowedUserIds();
        if (empty($followedIds)) {
            return ['items' => [], 'total' => 0];
        }

        $usersFilter = $this->parseCsvParam($request->get('users'));
        $filteredIds = $this->applyUserFilter($followedIds, $usersFilter);
        if (empty($filteredIds)) {
            return ['items' => [], 'total' => 0];
        }

        $actions = $this->parseCsvParam($request->get('actions'));
        $actions = $this->normalizeActions($actions);
        if ($actions !== null && empty($actions)) {
            return ['items' => [], 'total' => 0];
        }

        $dateFrom = $this->normalizeDate($request->get('dateFrom'));
        $dateTo = $this->normalizeDate($request->get('dateTo'));

        $query = Notification::find()
            ->alias('n')
            ->where(['n.user_id' => $filteredIds])
            ->with(['user', 'movie'])
            ->orderBy(['n.created_at' => SORT_DESC]);

        if ($actions !== null) {
            $query->andWhere(['n.action' => $actions]);
        }

        if ($dateFrom !== null) {
            $query->andWhere(['>=', 'n.created_at', $dateFrom . ' 00:00:00']);
        }

        if ($dateTo !== null) {
            $query->andWhere(['<=', 'n.created_at', $dateTo . ' 23:59:59']);
        }

        $total = (clone $query)->count();
        $items = $query
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->all();

        return [
            'items' => array_map([$this, 'serializeNotification'], $items),
            'total' => (int)$total,
        ];
    }

    public function actionStatus()
    {
        $followedIds = $this->getFollowedUserIds();
        if (empty($followedIds)) {
            return ['has_unread' => false, 'unread_count' => 0];
        }

        /** @var User $user */
        $user = Yii::$app->user->identity;
        $readAt = $user->notifications_read_at;

        $query = Notification::find()->where(['user_id' => $followedIds]);
        if (!empty($readAt)) {
            $query->andWhere(['>', 'created_at', $readAt]);
        }

        $unreadCount = (int)$query->count();

        return [
            'has_unread' => $unreadCount > 0,
            'unread_count' => $unreadCount,
        ];
    }

    public function actionRead()
    {
        /** @var User $user */
        $user = Yii::$app->user->identity;
        $user->notifications_read_at = gmdate('Y-m-d H:i:s');
        $user->save(false);

        return ['ok' => true];
    }

    public function actionUsers()
    {
        $followedIds = $this->getFollowedUserIds();
        if (empty($followedIds)) {
            return ['users' => []];
        }

        $activeIds = Notification::find()
            ->select(['user_id'])
            ->distinct()
            ->where(['user_id' => $followedIds])
            ->column();

        if (empty($activeIds)) {
            return ['users' => []];
        }

        $users = User::find()
            ->where(['id' => $activeIds, 'status' => User::STATUS_ACTIVE])
            ->orderBy(['username' => SORT_ASC])
            ->all();

        return [
            'users' => array_map([$this, 'serializeUser'], $users),
        ];
    }

    private function getFollowedUserIds(): array
    {
        $userId = (int)Yii::$app->user->id;

        return UserFollow::find()
            ->select(['followee_id'])
            ->where(['follower_id' => $userId])
            ->column();
    }

    private function applyUserFilter(array $followedIds, array $filterIds): array
    {
        if (empty($filterIds)) {
            return $followedIds;
        }

        $followedMap = array_fill_keys($followedIds, true);
        $filtered = [];
        foreach ($filterIds as $id) {
            $intId = (int)$id;
            if ($intId && isset($followedMap[$intId])) {
                $filtered[] = $intId;
            }
        }

        return array_values(array_unique($filtered));
    }

    private function normalizeActions(array $actions): ?array
    {
        if (empty($actions)) {
            return null;
        }

        $allowed = [Notification::ACTION_MOVIE_ADDED, Notification::ACTION_MOVIE_RATED];
        $normalized = [];
        foreach ($actions as $action) {
            if (in_array($action, $allowed, true)) {
                $normalized[$action] = true;
            }
        }

        return array_keys($normalized);
    }

    private function normalizeDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = (string)$value;
        $date = \DateTime::createFromFormat('Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new BadRequestHttpException('Invalid date format.');
        }

        return $value;
    }

    private function serializeNotification(Notification $notification): array
    {
        $user = $notification->user;
        $movie = $notification->movie;

        return [
            'id' => $notification->id,
            'action' => $notification->action,
            'created_at' => $this->formatCreatedAt($notification->created_at),
            'user' => $user ? $this->serializeUser($user) : null,
            'movie' => $movie ? $this->serializeMovie($movie) : null,
            'rating' => $notification->rating === null ? null : (int)$notification->rating,
        ];
    }

    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name ?? '',
            'avatar_url' => $this->getAvatarUrl($user),
        ];
    }

    private function serializeMovie(Movie $movie): array
    {
        return [
            'id' => $movie->id,
            'title' => $movie->title,
            'poster_url' => $this->getPosterUrl($movie),
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

    private function formatCreatedAt(string $createdAt): string
    {
        $timestamp = strtotime($createdAt);
        if ($timestamp === false) {
            return $createdAt;
        }

        return gmdate('c', $timestamp);
    }
}
