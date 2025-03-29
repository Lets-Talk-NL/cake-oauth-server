<?php

namespace OAuthServer\Lib\Enum;

use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use MyCLabs\Enum\Enum;
use OAuthServer\Lib\Enum\Traits\EnumTrait;
use OpenIDConnectServer\Repositories\IdentityProviderInterface;

/**
 * OAuth 2.0 repository requirements enumeration
 *
 * @method static Repository ACCESS_TOKEN()
 * @method static Repository AUTH_CODE()
 * @method static Repository CLIENT()
 * @method static Repository REFRESH_TOKEN()
 * @method static Repository SCOPE()
 * @method static Repository USER()
 * @method static Repository IDENTITY()
 */
class Repository extends Enum
{
    use EnumTrait;

    public const ACCESS_TOKEN  = AccessTokenRepositoryInterface::class;
    public const AUTH_CODE     = AuthCodeRepositoryInterface::class;
    public const CLIENT        = ClientRepositoryInterface::class;
    public const REFRESH_TOKEN = RefreshTokenRepositoryInterface::class;
    public const SCOPE         = ScopeRepositoryInterface::class;
    public const USER          = UserRepositoryInterface::class;
    public const IDENTITY      = IdentityProviderInterface::class;

    /**
     * Maps repositories to table locator alias defaults
     *
     * @return string|array
     */
    public static function aliasDefaults(?string $value = null)
    {
        $aliases = [
            static::ACCESS_TOKEN  => 'OAuthServer.AccessTokens',
            static::AUTH_CODE     => 'OAuthServer.AuthCodes',
            static::CLIENT        => 'OAuthServer.Clients',
            static::REFRESH_TOKEN => 'OAuthServer.RefreshTokens',
            static::SCOPE         => 'OAuthServer.Scopes',
            // application implementation (plugin has no users implementation)
            static::USER     => 'Users',
            static::IDENTITY => 'Users',
        ];
        return static::enum($value, $aliases);
    }

    /**
     * Maps repositories to readable names
     *
     * @return string|array
     */
    public static function labels(?string $value = null)
    {
        $labels = [
            static::ACCESS_TOKEN  => 'access token repository',
            static::AUTH_CODE     => 'auth code repository',
            static::CLIENT        => 'client repository',
            static::REFRESH_TOKEN => 'refresh token repository',
            static::SCOPE         => 'scope repository',
            static::USER          => 'user repository',
            static::IDENTITY      => 'user identity repository',
        ];
        return static::enum($value, $labels);
    }
}
