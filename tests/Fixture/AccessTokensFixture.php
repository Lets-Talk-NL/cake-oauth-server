<?php

namespace OAuthServer\Test\Fixture;

class AccessTokensFixture extends AbstractMigrationsTestFixture
{
    public $import = [
        'connection' => 'test_migrations',
        'model'      => 'OAuthServer.AccessTokens',
    ];
}
