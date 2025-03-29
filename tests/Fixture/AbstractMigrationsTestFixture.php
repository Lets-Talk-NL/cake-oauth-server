<?php

namespace OAuthServer\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;
use OAuthServer\Test\TestSuite\Lib\Factory;

abstract class AbstractMigrationsTestFixture extends TestFixture
{
    public function init()
    {
        Factory::migrations('test_migrations')->rollback();
        Factory::migrations('test_migrations')->migrate();
        parent::init();
    }
}
