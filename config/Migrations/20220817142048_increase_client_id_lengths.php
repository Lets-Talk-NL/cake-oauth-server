<?php

use Migrations\AbstractMigration;

/**
 * Increase client id maximum value length
 */
class IncreaseClientIdLengths extends AbstractMigration
{
    public function up()
    {
        $this->table('oauth_clients')
             ->changeColumn('id', 'string', ['limit' => 36])
             ->update();

        $this->table('oauth_access_tokens')
             ->changeColumn('client_id', 'string', ['limit' => 36])
             ->update();

        $this->table('oauth_auth_codes')
             ->changeColumn('client_id', 'string', ['limit' => 36])
             ->update();
    }

    public function down()
    {
        $this->table('oauth_clients')
             ->changeColumn('id', 'string', ['limit' => 20])
             ->update();

        $this->table('oauth_access_tokens')
             ->changeColumn('client_id', 'string', ['limit' => 20])
             ->update();

        $this->table('oauth_auth_codes')
             ->changeColumn('client_id', 'string', ['limit' => 20])
             ->update();
    }
}
