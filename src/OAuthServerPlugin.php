<?php

namespace OAuthServer;

use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Core\Plugin as CakePlugin;
use Cake\Event\Event;
use Cake\Event\EventDispatcherInterface;
use Cake\Event\EventDispatcherTrait;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Routing\RouteBuilder;
use DateInterval;
use function Functional\map;
use InvalidArgumentException;
use League\Event\EmitterAwareTrait;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Grant\GrantTypeInterface;
use League\OAuth2\Server\ResourceServer;
use LogicException;
use OAuthServer\Exception\Exception;
use OAuthServer\Lib\Enum\Extension;
use OAuthServer\Lib\Enum\GrantType;
use OAuthServer\Lib\Enum\Repository;
use OAuthServer\Lib\Enum\Token;
use OAuthServer\Lib\Factory;
use OAuthServer\Lib\Traits\RepositoryAwareTrait;
use OAuthServer\ORM\Locator\RepositoryLocator;
use OpenIDConnectServer\ClaimExtractor;

/**
 * OAuth 2.0 plugin object
 *
 * May construct more centrally plugin configured objects
 */
class OAuthServerPlugin extends BasePlugin implements EventDispatcherInterface
{
    use EmitterAwareTrait;
    use LocatorAwareTrait;
    use RepositoryAwareTrait;
    use EventDispatcherTrait;

    public function initialize(): void
    {
        $this->initializeTableLocator();
    }

    /**
     * Initialize the OAuth 2.0 server repository table locator
     */
    public function initializeTableLocator(): void
    {
        $configuredRepositories = Configure::read('OAuthServer.repositories') ?: [];
        $this->setTableLocator(new RepositoryLocator($configuredRepositories));
    }

    /**
     * Get the instance from the Cake application's plugin collection
     *
     * @throws LogicException
     */
    public static function instance(): self
    {
        $name = 'OAuthServer';
        if (!$plugin = CakePlugin::getCollection()->get($name)) {
            throw new LogicException(sprintf('plugin %s not loaded', $name));
        }
        return $plugin;
    }

    /**
     * Get the OAuth 2.0 server private key object
     */
    public function getPrivateKey(): ?CryptKey
    {
        $path     = Configure::read('OAuthServer.privateKey.path') ?? '';
        $password = Configure::read('OAuthServer.privateKey.password');
        return new CryptKey($path, $password);
    }

    /**
     * Get the OAuth 2.0 server public key object
     */
    public function getPublicKey(): CryptKey
    {
        $path = Configure::read('OAuthServer.publicKey.path') ?? '';
        return new CryptKey($path);
    }

    /**
     * Get the OAuth 2.0 server encryption key string
     *
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
     */
    public function getDefaultScope(): string
    {
        return Configure::read('OAuthServer.defaultScope') ?? '';
    }

    /**
     * Get the OAuth 2.0 server enabled grant objects
     *
     * @throws InvalidArgumentException
     * @throws Exception
     * @return GrantTypeInterface[]
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
        return map(Configure::read('OAuthServer.extensions') ?: [], fn ($extension) => new Extension($extension));
    }

    /**
     * Check if an implemented OAuth 2.0 is configured
     */
    public function hasConfiguredExtension(Extension $extension): bool
    {
        return in_array($extension->getValue(), map($this->getConfiguredExtensions(), fn (Extension $e) => $e->getValue()));
    }

    /**
     * Get the OAuth 2.0 authorization server handling object
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
            $claimExtractor     = $this->createOpenIDConnectClaimExtractor();
            $responseType       = Factory::openConnectIdTokenResponseType($identityRepository, $claimExtractor);
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
     * Creates an OpenID Connect ClaimExtractor object in
     * such a way that it may be externally modified by hooking
     * into the OAuthServer.createClaimsExtractor event.
     *
     * By default this extractor only extracts OpenID Connect Core 1.0 user data
     * claims excluding JWT specified claims (so excluding the 'sub')
     *
     * @link https://openid.net/specs/openid-connect-core-1_0.html#StandardClaims
     */
    public function createOpenIDConnectClaimExtractor(): ClaimExtractor
    {
        $claimExtractor = new ClaimExtractor();
        $this->getEventManager()->dispatch(new Event('OAuthServer.createClaimsExtractor', $claimExtractor));
        return $claimExtractor;
    }

    /**
     * Get the OAuth 2.0 resouce server handling object
     *
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
     * @throws InvalidArgumentException
     * @return DateInterval[] e.g. [Token::ACCESS_TOKEN => Object(DateInterval), ...]
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
     */
    public function getStatus(): array
    {
        $status = [];
        if ($clientRegistrationUrl = Configure::read('OAuthServer.clientRegistrationUrl')) {
            $status['client_registration_url'] = $clientRegistrationUrl;
        }
        $status['service_status']         = Configure::read('OAuthServer.serviceDisabled') ? 'disabled' : 'enabled';
        $status['grant_types']            = map(self::instance()->getGrantObjects(), fn (GrantTypeInterface $grant) => $grant->getIdentifier());
        $status['extensions']             = map(self::instance()->getConfiguredExtensions(), fn (Extension $ext) => Extension::labels($ext->getValue()));
        $status['refresh_tokens_enabled'] = (bool)Configure::read('OAuthServer.refreshTokensEnabled');
        $ttl                              = self::instance()->getTokensTimeToLiveIntervals();
        $status['token_ttl_seconds']      = map($ttl, fn (DateInterval $interval) => Factory::intervalTimestamp($interval));
        return $status;
    }

    public function routes(RouteBuilder $routes): void
    {
        parent::routes($routes);

        $routes->plugin('OAuthServer', ['path' => '/oauth'], function (RouteBuilder $routes) {
            $routes->connect('/', ['controller' => 'OAuth', 'action' => 'index']);
            $routes->connect('/authorize', ['controller' => 'OAuth', 'action' => 'authorize']);
            $routes->connect('/access_token', ['controller' => 'OAuth', 'action' => 'accessToken'], ['_ext' => ['json']]);
            $routes->connect('/status', ['controller' => 'OAuth', 'action' => 'status'], ['_ext' => ['json']]);
            $routes->connect('/userinfo', ['controller' => 'OAuth', 'action' => 'userInfo'], ['_ext' => ['json']]);
        });
    }

    public function getPath(): string
    {
        // @TODO for some reason path is not giving back trailing slash so add it back here but find out why sometime
        return rtrim(parent::getPath(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }
}
