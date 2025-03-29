<?php

namespace OAuthServer\Test\Fixture;

class ClientsFixture extends AbstractMigrationsTestFixture
{
    public $import = [
        'connection' => 'test_migrations',
        'model'      => 'OAuthServer.Clients',
    ];

    public $records = [
        [
            'id'            => 'TEST',
            'client_secret' => 'TestSecret',
            'name'          => 'Test',
            'redirect_uri'  => 'http://www.example.com',
        ],
    ];
}
