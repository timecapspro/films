<?php

namespace common\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * @property string $id
 * @property int $user_id
 * @property string $name
 * @property string $color
 * @property string $created_at
 * @property string $updated_at
 */
class Tag extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%tag}}';
    }

    public function rules()
    {
        return [
            [['user_id', 'name', 'color'], 'required'],
            [['user_id'], 'integer'],
            [['name'], 'string', 'max' => 120],
            [['color'], 'string', 'max' => 32],
            [['created_at', 'updated_at'], 'safe'],
        ];
    }

    public function getMovieTags()
    {
        return $this->hasMany(MovieTag::class, ['tag_id' => 'id']);
    }

    public function getMovies()
    {
        return $this->hasMany(Movie::class, ['id' => 'movie_id'])->via('movieTags');
    }

    public function beforeSave($insert)
    {
        if ($insert) {
            if (!$this->id) {
                $this->id = Yii::$app->security->generateRandomString(32);
            }
            $this->created_at = gmdate('Y-m-d H:i:s');
        }
        $this->updated_at = gmdate('Y-m-d H:i:s');

        return parent::beforeSave($insert);
    }
}
