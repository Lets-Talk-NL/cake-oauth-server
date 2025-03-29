<?php

namespace OAuthServer\Test\TestSuite\Lib;

use Migrations\Migrations;

/**
 * Testsuite helper factory
 */
class Factory
{
    /**
     * Builds the Migrations object for this plugin
     */
    public static function migrations(string $connection): Migrations
    {
        return new Migrations([
            'connection' => $connection,
            'plugin'     => 'OAuthServer',
        ]);
    }
}
