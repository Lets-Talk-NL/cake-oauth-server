<?php

namespace OAuthServer\Test\Fixture;

class AuthCodesFixture extends AbstractMigrationsTestFixture
{
    public $import = [
        'connection' => 'test_migrations',
        'model'      => 'OAuthServer.AuthCodes',
    ];
}
