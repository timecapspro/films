<?php

namespace common\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * @property string $id
 * @property int $user_id
 * @property string $list
 * @property string $title
 * @property int|null $year
 * @property int|null $runtime_min
 * @property string|null $genres_csv
 * @property string|null $description
 * @property string|null $notes
 * @property int $watched
 * @property int|null $rating
 * @property string|null $watched_at
 * @property string|null $poster_path
 * @property string|null $url
 * @property string|null $deleted_from_list
 * @property string $added_at
 * @property string $updated_at
 * @property Tag[] $tags
 */
class Movie extends ActiveRecord
{
    public const LIST_MY = 'my';
    public const LIST_LATER = 'later';
    public const LIST_DELETED = 'deleted';

    public static function tableName()
    {
        return '{{%movie}}';
    }

    public function rules()
    {
        return [
            [['list', 'title', 'user_id'], 'required'],
            ['list', 'in', 'range' => [self::LIST_MY, self::LIST_LATER, self::LIST_DELETED]],
            [['year'], 'integer', 'min' => 1880, 'max' => 2100],
            [['runtime_min'], 'integer', 'min' => 1, 'max' => 600],
            [['rating'], 'integer', 'min' => 1, 'max' => 10],
            [['watched'], 'boolean'],
            [['genres_csv', 'description', 'notes', 'poster_path', 'url', 'deleted_from_list'], 'string'],
            [['watched_at'], 'date', 'format' => 'php:Y-m-d'],
            ['title', 'string', 'max' => 255],
            [['added_at', 'updated_at'], 'safe'],
            ['rating', 'validateWatchedRequirements'],
        ];
    }

    public function getMovieTags()
    {
        return $this->hasMany(MovieTag::class, ['movie_id' => 'id']);
    }

    public function getTags()
    {
        return $this->hasMany(Tag::class, ['id' => 'tag_id'])->via('movieTags');
    }

    public function validateWatchedRequirements($attribute)
    {
        if ($this->watched) {
            if ($this->watched_at === null || $this->watched_at === '') {
                $this->addError('watched_at', 'watchedAt is required when watched.');
            }
        } else {
            if ($this->rating !== null || $this->watched_at !== null) {
                $this->rating = null;
                $this->watched_at = null;
            }
        }
    }

    public function beforeSave($insert)
    {
        if ($insert) {
            if (!$this->id) {
                $this->id = Yii::$app->security->generateRandomString(32);
            }
            $this->added_at = gmdate('Y-m-d H:i:s');
        }
        $this->updated_at = gmdate('Y-m-d H:i:s');

        return parent::beforeSave($insert);
    }
}
