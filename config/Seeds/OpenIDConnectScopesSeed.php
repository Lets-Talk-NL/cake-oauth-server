<?php

use Migrations\AbstractSeed;

/**
 * OpenIDConnectScopes seed.
 *
 * Can be applied by running the following:
 * ```
 * bin/cake migrations seed --plugin=OAuthServer --seed=OpenIDConnectScopesSeed
 * ```
 */
class OpenIDConnectScopesSeed extends AbstractSeed
{
    /**
     * @inheritDoc
     */
    public function run()
    {
        $table = $this->table('oauth_scopes');
        $table->insert([
            ['id' => 'openid', 'description' => 'Access to your user account'],
            ['id' => 'address', 'description' => 'Your address'],
            ['id' => 'email', 'description' => 'Your e-mail address'],
            ['id' => 'phone', 'description' => 'Your phone number'],
            ['id' => 'profile', 'description' => 'Your profile details'],
        ]);
        $table->saveData();
    }
}