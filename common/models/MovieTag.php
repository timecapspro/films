<?php

namespace common\models;

use yii\db\ActiveRecord;

/**
 * @property string $movie_id
 * @property string $tag_id
 */
class MovieTag extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%movie_tag}}';
    }

    public function rules()
    {
        return [
            [['movie_id', 'tag_id'], 'required'],
            [['movie_id', 'tag_id'], 'string', 'max' => 36],
        ];
    }
}
