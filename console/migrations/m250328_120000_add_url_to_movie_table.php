<?php

use yii\db\Migration;

class m250328_120000_add_url_to_movie_table extends Migration
{
    public function safeUp()
    {
        $this->addColumn('{{%movie}}', 'url', $this->string()->null());
    }

    public function safeDown()
    {
        $this->dropColumn('{{%movie}}', 'url');
    }
}
