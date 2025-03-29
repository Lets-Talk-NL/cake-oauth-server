<?php

namespace OAuthServer\Test\Fixture;

class AuthCodeScopesFixture extends AbstractMigrationsTestFixture
{
    public $import = [
        'connection' => 'test_migrations',
        'model'      => 'OAuthServer.AuthCodeScopes',
    ];
}
