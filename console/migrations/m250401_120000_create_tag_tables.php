<?php

use yii\db\Migration;

class m250401_120000_create_tag_tables extends Migration
{
    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%tag}}', [
            'id' => $this->string(36)->notNull(),
            'user_id' => $this->integer()->notNull(),
            'name' => $this->string(120)->notNull(),
            'color' => $this->string(32)->notNull(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ], $tableOptions);

        $this->addPrimaryKey('pk_tag', '{{%tag}}', 'id');
        $this->createIndex('idx_tag_user_id', '{{%tag}}', 'user_id');

        $this->addForeignKey(
            'fk_tag_user_id',
            '{{%tag}}',
            'user_id',
            '{{%user}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->createTable('{{%movie_tag}}', [
            'movie_id' => $this->string(36)->notNull(),
            'tag_id' => $this->string(36)->notNull(),
        ], $tableOptions);

        $this->addPrimaryKey('pk_movie_tag', '{{%movie_tag}}', ['movie_id', 'tag_id']);
        $this->createIndex('idx_movie_tag_movie_id', '{{%movie_tag}}', 'movie_id');
        $this->createIndex('idx_movie_tag_tag_id', '{{%movie_tag}}', 'tag_id');

        $this->addForeignKey(
            'fk_movie_tag_movie_id',
            '{{%movie_tag}}',
            'movie_id',
            '{{%movie}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk_movie_tag_tag_id',
            '{{%movie_tag}}',
            'tag_id',
            '{{%tag}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk_movie_tag_tag_id', '{{%movie_tag}}');
        $this->dropForeignKey('fk_movie_tag_movie_id', '{{%movie_tag}}');
        $this->dropTable('{{%movie_tag}}');

        $this->dropForeignKey('fk_tag_user_id', '{{%tag}}');
        $this->dropTable('{{%tag}}');
    }
}
