<?php

namespace OAuthServer;

use Cake\Core\Plugin as CakePlugin;
use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\ORM\Locator\LocatorAwareTrait;
use League\Event\EmitterAwareTrait;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Grant\GrantTypeInterface;
use League\OAuth2\Server\ResourceServer;
use OAuthServer\Lib\Enum\Extension;
use OAuthServer\Lib\Enum\GrantType;
use OAuthServer\Lib\Enum\Repository;
use OAuthServer\Lib\Enum\Token;
use OAuthServer\Lib\Factory;
use DateInterval;
use InvalidArgumentException;
use LogicException;
use OAuthServer\Exception\Exception;
use OAuthServer\Lib\Traits\RepositoryAwareTrait;
use OAuthServer\ORM\Locator\RepositoryLocator;
use function Functional\map;

/**
 * OAuth 2.0 plugin object
 *
 * May construct more centrally plugin configured objects
 */
class Plugin extends BasePlugin
{
    use EmitterAwareTrait;
    use LocatorAwareTrait;
    use RepositoryAwareTrait;

    /**
     * @inheritdoc
     */
    public function initialize()
    {
        $this->initializeTableLocator();
    }

    /**
     * Initialize the OAuth 2.0 server repository table locator
     *
     * @return void
     */
    public function initializeTableLocator(): void
    {
        $configuredRepositories = Configure::read('OAuthServer.repositories') ?: [];
        $this->setTableLocator(new RepositoryLocator($configuredRepositories));
    }

    /**
     * Get the instance from the Cake application's plugin collection
     *
     * @return Plugin
     * @throws LogicException
     */
    public static function instance(): Plugin
    {
        $name = 'OAuthServer';
        if (!$plugin = CakePlugin::getCollection()->get($name)) {
            throw new LogicException(sprintf('plugin %s not loaded', $name));
        }
        return $plugin;
    }

    /**
     * Get the OAuth 2.0 server private key object
     *
     * @return CryptKey
     */
    public function getPrivateKey(): ?CryptKey
    {
        $path     = Configure::read('OAuthServer.privateKey.path') ?? '';
        $password = Configure::read('OAuthServer.privateKey.password');
        return new CryptKey($path, $password);
    }

    /**
     * Get the OAuth 2.0 server public key object
     *
     * @return CryptKey
     */
    public function getPublicKey(): CryptKey
    {
        $path = Configure::read('OAuthServer.publicKey.path') ?? '';
        return new CryptKey($path);
    }

    /**
     * Get the OAuth 2.0 server encryption key string
     *
     * @return string
     * @throws LogicException
     */
    public function getEncryptionKey(): string
    {
        $key = Configure::read('OAuthServer.encryptionKey');
        if (!is_string($key)) {
            $key = (string)$key;
        }
        if (empty($key)) {
            throw new LogicException('missing encryption key');
        }
        return $key;
    }

    /**
     * Get the OAuth 2.0 server default scope string
     *
     * @return string
     */
    public function getDefaultScope(): string
    {
        return Configure::read('OAuthServer.defaultScope') ?? '';
    }

    /**
     * Get the OAuth 2.0 server enabled grant objects
     *
     * @return GrantTypeInterface[]
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function getGrantObjects(): array
    {
        $configuredGrantTypes = Configure::read('OAuthServer.grants') ?? [];
        $configuredTtl        = Configure::read('OAuthServer.ttl') ?? [];
        $repositoryLocator    = $this->getTableLocator();

        $privateKey    = $this->getPrivateKey();
        $encryptionKey = $this->getEncryptionKey();
        $defaultScope  = $this->getDefaultScope();
        $emitter       = $this->getEmitter();

        $grantObjects = [];

        foreach ($configuredGrantTypes as $grantType) {
            $grantObjects[] = Factory::grantObject(
                new GrantType($grantType),
                $privateKey,
                $encryptionKey,
                $defaultScope,
                $emitter,
                $configuredTtl,
                $repositoryLocator
            );
        }

        return $grantObjects;
    }

    /**
     * Get the OAuth 2.0 extensions that have been configured to be
     * activated within the plugin's implementations
     *
     * @return Extension[]
     */
    public function getConfiguredExtensions(): array
    {
        return map(Configure::read('OAuthServer.extensions') ?: [], fn($extension) => new Extension($extension));
    }

    /**
     * Check if an implemented OAuth 2.0 is configured
     *
     * @param Extension $extension
     * @return bool
     */
    public function hasConfiguredExtension(Extension $extension): bool
    {
        return in_array($extension->getValue(), map($this->getConfiguredExtensions(), fn(Extension $e) => $e->getValue()));
    }

    /**
     * Get the OAuth 2.0 authorization server handling object
     *
     * @return AuthorizationServer
     */
    public function getAuthorizationServer(): AuthorizationServer
    {
        $configuredRefreshTokens = Configure::read('OAuthServer.refreshTokensEnabled');
        $repositoryLocator       = $this->getTableLocator();
        $privateKey              = $this->getPrivateKey();
        $encryptionKey           = $this->getEncryptionKey();
        $ttl                     = $this->getTokensTimeToLiveIntervals();
        $responseType            = null;
        if ($this->hasConfiguredExtension(Extension::OPENID_CONNECT())) {
            $identityRepository = $this->getRepository(Repository::IDENTITY());
            $responseType       = Factory::openConnectIdTokenResponseType($identityRepository);
        }
        $server = Factory::authorizationServer($privateKey, $encryptionKey, $repositoryLocator, $responseType);
        foreach ($this->getGrantObjects() as $grantObject) {
            $server->enableGrantType($grantObject, $ttl[Token::ACCESS_TOKEN] ?? null);
        }
        $server->setEmitter($this->getEmitter());
        $server->revokeRefreshTokens($configuredRefreshTokens ?? true);
        return $server;
    }

    /**
     * Get the OAuth 2.0 resouce server handling object
     *
     * @return ResourceServer
     * @throws Exception
     */
    public function getResourceServer(): ResourceServer
    {
        $publicKey         = $this->getPublicKey();
        $repositoryLocator = $this->getTableLocator();
        return Factory::resourceServer($publicKey, $repositoryLocator);
    }

    /**
     * Get the token time to live DateInterval objects by token type enum key
     *
     * @return DateInterval[] e.g. [Token::ACCESS_TOKEN => Object(DateInterval), ...]
     * @throws InvalidArgumentException
     */
    public function getTokensTimeToLiveIntervals(): array
    {
        $mapping = Configure::read('OAuthServer.ttl') ?? [];
        return Factory::timeToLiveIntervals($mapping);
    }

    /**
     * Get status parameters
     *
     *   service_status: 'disabled' or 'enabled'
     *   grant_types: ['authorization_code']
     *   extensions: ['openid_connect']
     *   refresh_tokens_enabled: true or false
     *   token_ttl_seconds: ['access_token': 86400, 'refresh_token': 86400, ...]
     *
     * @return array
     */
    public function getStatus(): array
    {
        $status = [];
        if ($clientRegistrationUrl = Configure::read('OAuthServer.clientRegistrationUrl')) {
            $status['client_registration_url'] = $clientRegistrationUrl;
        }
        $status['service_status']         = Configure::read('OAuthServer.serviceDisabled') ? 'disabled' : 'enabled';
        $status['grant_types']            = map(Plugin::instance()->getGrantObjects(), fn(GrantTypeInterface $grant) => $grant->getIdentifier());
        $status['extensions']             = map(Plugin::instance()->getConfiguredExtensions(), fn(Extension $ext) => Extension::labels($ext->getValue()));
        $status['refresh_tokens_enabled'] = !!Configure::read('OAuthServer.refreshTokensEnabled');
        $ttl                              = Plugin::instance()->getTokensTimeToLiveIntervals();
        $status['token_ttl_seconds']      = map($ttl, fn(DateInterval $interval) => Factory::intervalTimestamp($interval));
        return $status;
    }

    /**
     * @inheritDoc
     */
    public function getPath()
    {
        // @TODO for some reason path is not giving back trailing slash so add it back here but find out why sometime
        return rtrim(parent::getPath(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }
}