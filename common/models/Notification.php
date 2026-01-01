<?php

namespace common\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * @property string $id
 * @property int $user_id
 * @property string $movie_id
 * @property string $action
 * @property int|null $rating
 * @property string $created_at
 */
class Notification extends ActiveRecord
{
    public const ACTION_MOVIE_ADDED = 'movie_added';
    public const ACTION_MOVIE_RATED = 'movie_rated';

    public static function tableName()
    {
        return '{{%notification}}';
    }

    public function rules()
    {
        return [
            [['user_id', 'movie_id', 'action', 'created_at'], 'required'],
            [['user_id', 'rating'], 'integer'],
            ['movie_id', 'string', 'max' => 36],
            ['action', 'string', 'max' => 32],
            [['created_at'], 'safe'],
            ['action', 'in', 'range' => [self::ACTION_MOVIE_ADDED, self::ACTION_MOVIE_RATED]],
        ];
    }

    public function beforeSave($insert)
    {
        if ($insert) {
            if (!$this->id) {
                $this->id = Yii::$app->security->generateRandomString(32);
            }
            $this->created_at = gmdate('Y-m-d H:i:s');
        }

        return parent::beforeSave($insert);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getMovie()
    {
        return $this->hasOne(Movie::class, ['id' => 'movie_id']);
    }
}
