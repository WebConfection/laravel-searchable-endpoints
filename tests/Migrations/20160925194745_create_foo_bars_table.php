<?php

use Phinx\Migration\AbstractMigration;

class CreateFooBarsTable extends AbstractMigration
{
    /**
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-abstractmigration-class
     *
     * The following commands can be used in this method and Phinx will
     * automatically reverse them when rolling back:
     *
     *    createTable
     *    renameTable
     *    addColumn
     *    renameColumn
     *    addIndex
     *    addForeignKey
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */

    /**
     * Migrate Up.
     */
    public function up()
    {
        // create the table
        $table = $this->table('foobars');
        $table->addColumn('id', 'integer')
            ->addColumn('body', 'string')
            ->addColumn('created_at', 'datetime')
            ->create();
    }

    /**
     * Migrate Down.
     */
    public function down()
    {

    }

}
