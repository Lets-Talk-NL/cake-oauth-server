<?php

namespace OAuthServer\Test\Fixture;

class ScopesFixture extends AbstractMigrationsTestFixture
{
    public $import = [
        'connection' => 'test_migrations',
        'model'      => 'OAuthServer.Scopes',
    ];

    public $records = [
        ['id' => 'test', 'description' => 'Default scope'],
        ['id' => 'openid', 'description' => ''],
        ['id' => 'profile', 'description' => ''],
        ['id' => 'email', 'description' => ''],
        ['id' => 'address', 'description' => ''],
        ['id' => 'phone', 'description' => ''],
    ];
}
