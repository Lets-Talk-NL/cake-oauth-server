<?php

use Migrations\AbstractMigration;

/**
 * Remove oauth_clients parent relationship causing confusion
 * and is unnecessary compared to other forms of relationships
 */
class OAuthClientsRemoveParentRelationship extends AbstractMigration
{
    /**
     * @inheritDoc
     */
    public function up()
    {
        $this->table('oauth_clients')
             ->removeColumn('parent_model')
             ->removeColumn('parent_id')
             ->update();
    }

    /**
     * @inheritDoc
     */
    public function down()
    {
        $this->table('oauth_clients')
             ->addColumn('parent_model', 'string', ['default' => null, 'limit' => 200, 'null' => true])
             ->addColumn('parent_id', 'integer', ['default' => null, 'limit' => 11, 'null' => true])
             ->update();
    }
}
