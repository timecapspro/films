<?php

namespace backend\controllers;

use backend\components\JwtAuthFilter;
use common\models\MovieTag;
use common\models\Tag;
use Yii;
use yii\filters\ContentNegotiator;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class ApiTagsController extends Controller
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
                    'create' => ['post'],
                    'update' => ['patch'],
                    'delete' => ['delete'],
                ],
            ],
        ]);
    }

    public function actionIndex()
    {
        $request = Yii::$app->request;
        $page = max((int)$request->get('page', 1), 1);
        $pageSize = (int)$request->get('pageSize', 10);
        $pageSize = $pageSize > 0 ? $pageSize : 10;
        $pageSize = min($pageSize, 100);

        $query = Tag::find()
            ->where(['user_id' => Yii::$app->user->id])
            ->orderBy(['created_at' => SORT_DESC]);

        $total = (clone $query)->count();
        $items = $query
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->all();

        return [
            'items' => array_map([$this, 'serializeTag'], $items),
            'total' => (int)$total,
        ];
    }

    public function actionCreate()
    {
        $data = Yii::$app->request->bodyParams;
        $tag = new Tag();
        $tag->user_id = Yii::$app->user->id;
        $tag->name = trim((string)($data['name'] ?? ''));
        $tag->color = (string)($data['color'] ?? '');

        if (!$tag->save()) {
            Yii::$app->response->statusCode = 422;
            return ['errors' => $tag->getErrors()];
        }

        return ['tag' => $this->serializeTag($tag)];
    }

    public function actionUpdate($id)
    {
        $tag = $this->findTag($id);
        $data = Yii::$app->request->bodyParams;

        if (array_key_exists('name', $data)) {
            $tag->name = trim((string)$data['name']);
        }
        if (array_key_exists('color', $data)) {
            $tag->color = (string)$data['color'];
        }

        if (!$tag->save()) {
            Yii::$app->response->statusCode = 422;
            return ['errors' => $tag->getErrors()];
        }

        return ['tag' => $this->serializeTag($tag)];
    }

    public function actionDelete($id)
    {
        $tag = $this->findTag($id);
        MovieTag::deleteAll(['tag_id' => $tag->id]);
        $tag->delete();

        return ['ok' => true];
    }

    private function findTag(string $id): Tag
    {
        $tag = Tag::findOne(['id' => $id, 'user_id' => Yii::$app->user->id]);
        if ($tag === null) {
            throw new NotFoundHttpException('Tag not found.');
        }

        return $tag;
    }

    private function serializeTag(Tag $tag): array
    {
        return [
            'id' => $tag->id,
            'name' => $tag->name,
            'color' => $tag->color,
            'created_at' => $tag->created_at,
        ];
    }
}
