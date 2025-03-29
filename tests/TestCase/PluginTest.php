<?php

namespace OAuthServer\Test\TestCase\Controller;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use DateInterval;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Grant\GrantTypeInterface;
use League\OAuth2\Server\Repositories\RepositoryInterface;
use League\OAuth2\Server\ResourceServer;
use OAuthServer\Lib\Enum\Extension;
use OAuthServer\Lib\Enum\Repository;
use OAuthServer\Lib\Enum\Token;
use OAuthServer\OAuthServerPlugin;
use OAuthServer\ORM\Locator\RepositoryLocator;

/**
 * Based off the default config
 */
class PluginTest extends TestCase
{
    protected OAuthServerPlugin $plugin;

    public function setUp()
    {
        parent::setUp();
        $this->plugin = new OAuthServerPlugin([]);
    }

    public function testInstance(): void
    {
        $this->assertInstanceOf(OAuthServerPlugin::class, OAuthServerPlugin::instance());
    }

    public function testInitialize(): void
    {
        $this->plugin->initialize();
        $this->assertInstanceOf(RepositoryLocator::class, $this->plugin->getTableLocator());
    }

    public function testInitializeTableLocator(): void
    {
        $this->plugin->initializeTableLocator();
        $this->assertInstanceOf(RepositoryLocator::class, $this->plugin->getTableLocator());
    }

    public function testGetPrivateKey(): void
    {
        $this->assertInstanceOf(CryptKey::class, $this->plugin->getPrivateKey());
    }

    public function testGetPublicKey(): void
    {
        $this->assertInstanceOf(CryptKey::class, $this->plugin->getPublicKey());
    }

    public function testGetEncryptionKey(): void
    {
        $this->assertInternalType('string', $this->plugin->getEncryptionKey());
    }

    public function testGetDefaultScope(): void
    {
        $defaultScope = $this->plugin->getDefaultScope();
        $this->assertInternalType('string', $defaultScope);
        $this->assertEquals('', $defaultScope);
    }

    public function testGetGrantObjects(): void
    {
        $grantObjects = $this->plugin->getGrantObjects();
        $this->assertInternalType('array', $grantObjects);
        foreach ($grantObjects as $grantObject) {
            $this->assertInstanceOf(GrantTypeInterface::class, $grantObject);
        }
    }

    public function testGetConfiguredExtensions(): void
    {
        $this->assertEquals(array_values(Extension::toArray()), Configure::read('OAuthServer.extensions'));
    }

    public function testHasConfiguredExtension(): void
    {
        $this->assertTrue($this->plugin->hasConfiguredExtension(Extension::OPENID_CONNECT()));
    }

    public function testGetAuthorizationServer(): void
    {
        $extensions = Configure::read('OAuthServer.extensions');
        Configure::write('OAuthServer.extensions', null);
        $this->assertInstanceOf(AuthorizationServer::class, $this->plugin->getAuthorizationServer());
        Configure::write('OAuthServer.extensions', $extensions);
        $this->assertInstanceOf(AuthorizationServer::class, $this->plugin->getAuthorizationServer());
    }

    public function testGetResourceServer(): void
    {
        $this->assertInstanceOf(ResourceServer::class, $this->plugin->getResourceServer());
    }

    public function testGetRepository(): void
    {
        foreach (Repository::values() as $enum) {
            $this->assertInstanceOf(Repository::class, $enum);
            $this->assertInstanceOf(RepositoryInterface::class, $this->plugin->getRepository($enum));
        }
    }

    public function testGetTokensTimeToLive(): void
    {
        $ttl = $this->plugin->getTokensTimeToLiveIntervals();
        $this->assertInternalType('array', $ttl);
        foreach (Token::toArray() as $type) {
            $this->assertArrayHasKey($type, $ttl);
            $this->assertInstanceOf(DateInterval::class, $ttl[$type]);
        }
    }

    public function testGetStatus(): void
    {
        $status = $this->plugin->getStatus();
        $this->assertInternalType('array', $status);
        foreach (['service_status', 'grant_types', 'extensions', 'refresh_tokens_enabled', 'token_ttl_seconds'] as $key) {
            $this->assertArrayHasKey($key, $status);
        }
    }

    public function testGetPath(): void
    {
        $this->assertInternalType('string', $this->plugin->getPath());
    }
}
