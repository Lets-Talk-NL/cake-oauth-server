<?php

namespace OAuthServer\Lib;

use Cake\ORM\Locator\LocatorInterface;
use Cake\ORM\TableRegistry;
use Cake\Utility\Security;
use Cake\Utility\Text;
use League\Event\EmitterInterface;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\AuthorizationValidators\AuthorizationValidatorInterface;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Grant\AbstractGrant;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\GrantTypeInterface;
use League\OAuth2\Server\Grant\ImplicitGrant;
use League\OAuth2\Server\Grant\PasswordGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\Repositories\RepositoryInterface;
use League\OAuth2\Server\ResourceServer;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use OAuthServer\Exception\Exception;
use OAuthServer\Lib\Enum\GrantType;
use OAuthServer\Lib\Enum\Repository;
use InvalidArgumentException;
use DateInterval;
use DateTime;
use OAuthServer\Lib\Enum\Token;
use OpenIDConnectServer\ClaimExtractor;
use OpenIDConnectServer\IdTokenResponse;
use OpenIDConnectServer\Repositories\IdentityProviderInterface;
use function Functional\map;

/**
 * OAuth 2.0 object factory
 *
 * May construct more generally OAuth 2.0 related objects
 */
class Factory
{
    /**
     * Creates a new unique client ID
     *
     * @return string e.g. NGYcZDRjODcxYzFkY2Rk (seems popular format but may be any arbitrary string)
     */
    public static function clientId(): string
    {
        return base64_encode(uniqid() . substr(uniqid(), 11, 2));
    }

    /**
     * Creates a new unique client secret
     *
     * @return string
     */
    public static function clientSecret(): string
    {
        return Security::hash(Text::uuid(), 'sha1', true);
    }

    /**
     * Get OAuth 2.0 time to live DateInterval object
     *
     * @param string|DateInterval $duration e.g. 'P1M' (every 1 month)
     * @return DateInterval
     * @throws InvalidArgumentException
     */
    public static function dateInterval($duration): DateInterval
    {
        if ($duration instanceof DateInterval) {
            return $duration;
        }
        if (!is_string($duration)) {
            throw new InvalidArgumentException('expected string duration');
        }
        try {
            return new DateInterval($duration);
        } catch (Exception $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Get OAuth 2.0 time to live DateInterval objects based
     * on an array with interval specification strings
     *
     * @param array $durations e.g. [Token::ACCESS_TOKEN => 'P1M']
     * @return DateInterval[] e.g. [Token::ACCESS_TOKEN => Object(DateInterval)]
     * @throws InvalidArgumentException
     */
    public static function timeToLiveIntervals(array $durations): array
    {
        $types     = Token::rawValues();
        $defaults  = array_fill_keys($types, 'P1D');
        $durations += $defaults; // replenish mapping from defaults
        $durations = array_intersect_key($durations, $defaults); // only keys from defaults
        return map($durations, fn($duration) => static::dateInterval($duration));
    }

    /**
     * Converts DateInterval object into UNIX timestamp
     *
     * @param DateInterval $interval
     * @return int Seconds since the Unix Epoch (January 1 1970 00:00:00 GMT)
     */
    public static function intervalTimestamp(DateInterval $interval): int
    {
        $now  = new DateTime();
        $then = clone $now;
        $then = $then->add($interval);
        return $then->getTimestamp() - $now->getTimestamp();
    }

    /**
     * Completes the provided repository mapping with defaults
     *
     * @param array $inputMapping e.g. [Repository::AUTH_CODE => 'MyPlugin.MyTable']
     * @return array e.g. [... (defaults), Repository::AUTH_CODE => 'MyPlugin.MyTable', ... (defaults)]
     */
    public static function completeRepositoryMapping(array $inputRepositoryMapping): array
    {
        $defaults               = Repository::aliasDefaults();
        $inputRepositoryMapping += $defaults; // replenish mapping from defaults
        $inputRepositoryMapping = array_intersect_key($inputRepositoryMapping, $defaults); // only keys from defaults
        return $inputRepositoryMapping;
    }

    /**
     * Get OAuth 2.0 grant object of given type
     *
     * @param GrantType        $grantType
     * @param CryptKey         $privateKey
     * @param EmitterInterface $emitter
     * @param string           $encryptionKey     e.g. 'lxZFUEsBCJ2Yb14IF2ygAHI5N4+ZAUXXaSeeJm6+twsUmIen'
     * @param string           $defaultScope      e.g. 'defaultscopename1 defaultscopename2'
     * @param array            $ttlMapping        e.g. [Token::ACCESS_TOKEN => 'P1D', ...]
     * @param array            $repositoryMapping e.g. [Repository::AUTH_CODE => 'MyPlugin.MyTable', ...]
     * @return GrantTypeInterface
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public static function grantObject(
        GrantType $grantType,
        CryptKey $privateKey,
        string $encryptionKey,
        string $defaultScope,
        EmitterInterface $emitter,
        array $ttlMapping,
        LocatorInterface $repositories
    ): GrantTypeInterface {
        $ttl = static::timeToLiveIntervals($ttlMapping);
        /** @var AbstractGrant $grantObject */
        switch ($grantType->getValue()) {
            case GrantType::AUTHORIZATION_CODE:
                $grantObject = new AuthCodeGrant(
                    $repositories->get(Repository::AUTH_CODE),
                    $repositories->get(Repository::REFRESH_TOKEN),
                    $ttl[Token::AUTHENTICATION_TOKEN]
                );
                break;
            case GrantType::REFRESH_TOKEN:
                $grantObject = new RefreshTokenGrant($repositories->get(Repository::REFRESH_TOKEN));
                break;
            case GrantType::IMPLICIT:
                $grantObject = new ImplicitGrant($ttl[Token::ACCESS_TOKEN]);
                break;
            case GrantType::PASSWORD:
                $grantObject = new PasswordGrant($repositories->get(Repository::USER), $repositories->get(Repository::REFRESH_TOKEN));
                break;
            default:
                $grantClassName = GrantType::classNames($grantType->getValue());
                $grantObject    = new $grantClassName();
        }
        $grantObject->setPrivateKey($privateKey);
        $grantObject->setAccessTokenRepository($repositories->get(Repository::ACCESS_TOKEN));
        $grantObject->setAuthCodeRepository($repositories->get(Repository::AUTH_CODE));
        $grantObject->setClientRepository($repositories->get(Repository::CLIENT));
        $grantObject->setScopeRepository($repositories->get(Repository::SCOPE));
        $grantObject->setUserRepository($repositories->get(Repository::USER));
        $grantObject->setRefreshTokenRepository($repositories->get(Repository::REFRESH_TOKEN));
        $grantObject->setRefreshTokenTTL($ttl[Token::REFRESH_TOKEN]);
        $grantObject->setDefaultScope($defaultScope);
        $grantObject->setEncryptionKey($encryptionKey);
        $grantObject->setEmitter($emitter);
        return $grantObject;
    }

    /**
     * Get OAuth 2.0 OpenID Connect ID extension response type object
     *
     * @param IdentityProviderInterface $identityProvider
     * @param ClaimExtractor            $claimExtractor
     * @return ResponseTypeInterface
     */
    public static function openConnectIdTokenResponseType(IdentityProviderInterface $identityProvider, ClaimExtractor $claimExtractor): ResponseTypeInterface
    {
        return new IdTokenResponse($identityProvider, $claimExtractor);
    }

    /**
     * Get OAuth 2.0 authorization server with the given private key
     *
     * @param CryptKey                   $privateKey
     * @param string                     $encryptionKey e.g. 'lxZFUEsBCJ2Yb14IF2ygAHI5N4+ZAUXXaSeeJm6+twsUmIen'
     * @param LocatorInterface           $repositories  e.g. [Repository::AUTH_CODE => 'MyPlugin.MyTable', ...]
     * @param ResponseTypeInterface|null $responseType
     * @return void
     */
    public static function authorizationServer(
        CryptKey $privateKey,
        string $encryptionKey,
        LocatorInterface $repositories,
        ResponseTypeInterface $responseType = null
    ): AuthorizationServer {
        return new AuthorizationServer(
            $repositories->get(Repository::CLIENT),
            $repositories->get(Repository::ACCESS_TOKEN),
            $repositories->get(Repository::SCOPE),
            $privateKey,
            $encryptionKey,
            $responseType
        );
    }

    /**
     * Get OAuth 2.0 resource server with the given public key
     *
     * @param CryptKey                             $publicKey
     * @param LocatorInterface                     $repositories
     * @param AuthorizationValidatorInterface|null $authorizationValidator
     * @return ResourceServer
     * @throws Exception
     */
    public static function resourceServer(
        CryptKey $publicKey,
        LocatorInterface $repositories,
        ?AuthorizationValidatorInterface $authorizationValidator = null
    ): ResourceServer {
        return new ResourceServer(
            $repositories->get(Repository::ACCESS_TOKEN),
            $publicKey,
            $authorizationValidator
        );
    }
}