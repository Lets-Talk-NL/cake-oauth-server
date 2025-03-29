<?php

namespace OAuthServer\Test\Fixture;

class RefreshTokensFixture extends AbstractMigrationsTestFixture
{
    public $import = [
        'connection' => 'test_migrations',
        'model'      => 'OAuthServer.RefreshTokens',
    ];
}
