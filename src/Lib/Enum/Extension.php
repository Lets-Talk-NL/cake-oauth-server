<?php

namespace OAuthServer\Lib\Enum;

use MyCLabs\Enum\Enum;
use OAuthServer\Lib\Enum\Traits\EnumTrait;

/**
 * OAuth 2.0 extensions enumeration
 *
 * @method static OPENID_CONNECT()
 */
class Extension extends Enum
{
    use EnumTrait;

    public const OPENID_CONNECT = 'openid_connect';

    /**
     * Maps extensions to normal names
     *
     * @return string|array
     */
    public static function labels(?string $value = null)
    {
        $labels = [
            static::OPENID_CONNECT => 'OpenID Connect',
        ];
        return static::enum($value, $labels);
    }
}
