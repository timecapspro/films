<?php

use yii\db\Migration;

class m250415_120000_add_follow_and_notifications extends Migration
{
    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%user_follow}}', [
            'follower_id' => $this->integer()->notNull(),
            'followee_id' => $this->integer()->notNull(),
            'created_at' => $this->dateTime()->notNull(),
        ], $tableOptions);

        $this->addPrimaryKey('pk_user_follow', '{{%user_follow}}', ['follower_id', 'followee_id']);
        $this->createIndex('idx_user_follow_follower', '{{%user_follow}}', 'follower_id');
        $this->createIndex('idx_user_follow_followee', '{{%user_follow}}', 'followee_id');

        $this->addForeignKey(
            'fk_user_follow_follower',
            '{{%user_follow}}',
            'follower_id',
            '{{%user}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk_user_follow_followee',
            '{{%user_follow}}',
            'followee_id',
            '{{%user}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->createTable('{{%notification}}', [
            'id' => $this->string(32)->notNull(),
            'user_id' => $this->integer()->notNull(),
            'movie_id' => $this->string(36)->notNull(),
            'action' => $this->string(32)->notNull(),
            'rating' => $this->integer()->null(),
            'created_at' => $this->dateTime()->notNull(),
        ], $tableOptions);

        $this->addPrimaryKey('pk_notification', '{{%notification}}', 'id');
        $this->createIndex('idx_notification_user_id', '{{%notification}}', 'user_id');
        $this->createIndex('idx_notification_action', '{{%notification}}', 'action');
        $this->createIndex('idx_notification_created_at', '{{%notification}}', 'created_at');
        $this->createIndex('idx_notification_movie_id', '{{%notification}}', 'movie_id');

        $this->addForeignKey(
            'fk_notification_user',
            '{{%notification}}',
            'user_id',
            '{{%user}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk_notification_movie',
            '{{%notification}}',
            'movie_id',
            '{{%movie}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addColumn('{{%user}}', 'notifications_read_at', $this->dateTime()->null());
        $this->createIndex('idx_user_notifications_read_at', '{{%user}}', 'notifications_read_at');
    }

    public function safeDown()
    {
        $this->dropIndex('idx_user_notifications_read_at', '{{%user}}');
        $this->dropColumn('{{%user}}', 'notifications_read_at');

        $this->dropForeignKey('fk_notification_movie', '{{%notification}}');
        $this->dropForeignKey('fk_notification_user', '{{%notification}}');
        $this->dropTable('{{%notification}}');

        $this->dropForeignKey('fk_user_follow_followee', '{{%user_follow}}');
        $this->dropForeignKey('fk_user_follow_follower', '{{%user_follow}}');
        $this->dropTable('{{%user_follow}}');
    }
}
