<?php

namespace OAuthServer\Test\Fixture;

class AccessTokenScopesFixture extends AbstractMigrationsTestFixture
{
    public $import = [
        'connection' => 'test_migrations',
        'model'      => 'OAuthServer.AccessTokenScopes',
    ];
}
