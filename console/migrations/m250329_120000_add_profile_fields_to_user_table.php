<?php

use yii\db\Migration;

/**
 * Handles adding profile fields to table `{{%user}}`.
 */
class m250329_120000_add_profile_fields_to_user_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('{{%user}}', 'name', $this->string());
        $this->addColumn('{{%user}}', 'avatar_path', $this->string());
        $this->addColumn('{{%user}}', 'about', $this->string(400));
        $this->addColumn('{{%user}}', 'gender', $this->string(1));
        $this->addColumn('{{%user}}', 'birth_date', $this->date());
        $this->addColumn('{{%user}}', 'is_public', $this->boolean()->notNull()->defaultValue(1));
        $this->createIndex('idx-user-is_public', '{{%user}}', 'is_public');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropIndex('idx-user-is_public', '{{%user}}');
        $this->dropColumn('{{%user}}', 'is_public');
        $this->dropColumn('{{%user}}', 'birth_date');
        $this->dropColumn('{{%user}}', 'gender');
        $this->dropColumn('{{%user}}', 'about');
        $this->dropColumn('{{%user}}', 'avatar_path');
        $this->dropColumn('{{%user}}', 'name');
    }
}
