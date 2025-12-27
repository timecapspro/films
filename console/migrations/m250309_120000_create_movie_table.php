<?php

use yii\db\Migration;

class m250309_120000_create_movie_table extends Migration
{
    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%movie}}', [
            'id' => $this->string(36)->notNull(),
            'user_id' => $this->integer()->notNull(),
            'list' => $this->string(16)->notNull(),
            'title' => $this->string()->notNull(),
            'year' => $this->integer()->null(),
            'runtime_min' => $this->integer()->null(),
            'genres_csv' => $this->text()->null(),
            'description' => $this->text()->null(),
            'notes' => $this->text()->null(),
            'watched' => $this->boolean()->notNull()->defaultValue(false),
            'rating' => $this->integer()->null(),
            'watched_at' => $this->date()->null(),
            'poster_path' => $this->string()->null(),
            'deleted_from_list' => $this->string(16)->null(),
            'added_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ], $tableOptions);

        $this->addPrimaryKey('pk_movie', '{{%movie}}', 'id');
        $this->createIndex('idx_movie_user_id', '{{%movie}}', 'user_id');
        $this->createIndex('idx_movie_list', '{{%movie}}', 'list');
        $this->createIndex('idx_movie_title', '{{%movie}}', 'title');

        $this->addForeignKey(
            'fk_movie_user_id',
            '{{%movie}}',
            'user_id',
            '{{%user}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk_movie_user_id', '{{%movie}}');
        $this->dropTable('{{%movie}}');
    }
}
