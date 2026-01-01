<?php

namespace common\models;

use yii\db\ActiveRecord;

/**
 * @property int $follower_id
 * @property int $followee_id
 * @property string $created_at
 */
class UserFollow extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%user_follow}}';
    }

    public function rules()
    {
        return [
            [['follower_id', 'followee_id', 'created_at'], 'required'],
            [['follower_id', 'followee_id'], 'integer'],
            [['created_at'], 'safe'],
        ];
    }

    public function getFollower()
    {
        return $this->hasOne(User::class, ['id' => 'follower_id']);
    }

    public function getFollowee()
    {
        return $this->hasOne(User::class, ['id' => 'followee_id']);
    }
}
